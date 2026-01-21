<?php
session_start();
require_once 'config.php';

/**
 * Script de synchronisation alternatif/manuel.
 * Note : La synchronisation principale est gérée par admin.php via import/run_all_imports.php.
 * Ce fichier est conservé comme point d'entrée supplémentaire si nécessaire.
 */

// Vérification authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html');
    exit;
}

// LOGIQUE DE SYNCHRONISATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sync'])) {
    ini_set("max_execution_time", 300);
    $has_error = false;
    $steps_log = [];
    $message_sync = "";
    $message_type = "";

    // Connexion PDO
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $_SESSION['sync_message'] = "Erreur connexion BDD: " . $e->getMessage();
        $_SESSION['sync_type'] = "error";
        header('Location: admin.php');
        exit;
    }

    try {
        // ETAPE 1 : AUTO-DEZIP (Silencieux si réussi)
        $zipFile = 'SAE_json.zip';
        $targetDir = __DIR__ . '/uploads/saejson/';
        
        if (file_exists($zipFile)) {
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                $zip->extractTo($targetDir);
                $zip->close();
                // On ne loggue plus le succès du dézip pour l'utilisateur
            } else {
                $steps_log[] = "Erreur technique (Zip corrompu).";
                $has_error = true;
            }
        }

        // ETAPE 2 : IMPORTS
        $folder = __DIR__ . "/uploads/SAE_json/";
        if (!is_dir($folder)) {
            $folder = __DIR__ . "/uploads/saejson/";
            if (is_dir($folder . "SAE_json")) $folder = $folder . "SAE_json/";
        }

        if (!is_dir($folder)) {
            $message_sync = "Dossier de données introuvable.";
            $message_type = "error";
        } else {
            $files = glob($folder . "*.json");
            if (empty($files)) $files = glob($folder . "*/*.json");

            if (empty($files)) {
                $message_sync = "Aucun fichier de données trouvé.";
                $message_type = "error";
            } else {
                // Requetes SQL - INSERT IGNORE pour eviter les doublons
                $sqlInsertEtudiant = $pdo->prepare("INSERT IGNORE INTO etudiant (code_nip, code_ine, etudid_scodoc) VALUES (:nip, :ine, :etud)");
                $sqlInsertSemestre = $pdo->prepare("INSERT IGNORE INTO semestre_instance (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite) VALUES (:idfs, :idf, :annee, :num, :modalite)");
                $sqlInsertInscription = $pdo->prepare("INSERT IGNORE INTO inscription (code_nip, id_formsemestre, decision_jury, decision_annee, etat_inscription, pcn_competences, is_apc, date_maj) VALUES (:nip, :fs, :jury, :annee, :etat, :pct, :isapc, :maj)");
                $sqlInsertCompetence = $pdo->prepare("INSERT IGNORE INTO resultat_competence (id_inscription, numero_competence, code_decision, moyenne) VALUES (:insc, :num, :code, :moy)");

                $count = 0;
                foreach ($files as $file) {
                    $json = file_get_contents($file);
                    $data = json_decode($json, true);
                    if (!is_array($data)) continue;

                    preg_match('/fs_(\d+)/', basename($file), $m);
                    $id_formsemestre = $m[1] ?? null;
                    if (!$id_formsemestre) continue;

                    // RECUPERATION / INSERTION FORMATION (AVEC CORRECTION ENCODAGE)
                    $formationTitre = null;
                    
                    // PRIORITE 1: Utiliser le titre du JSON (contient les accents corrects)
                    if (isset($data[0]["formation"]["titre"]) && !empty($data[0]["formation"]["titre"])) {
                        $formationTitre = trim($data[0]["formation"]["titre"]);
                    }
                    // PRIORITE 2: Fallback sur le nom de fichier
                    elseif (preg_match('/fs_\d+_(.*?)\.json$/i', basename($file), $matches)) {
                        $formationTitre = $matches[1];
                    } else {
                        $formationTitre = "Formation Inconnue"; 
                    }
                
                    // NORMALISATION DES NOMS DE FORMATIONS
                    // Objectif : noms simples comme "BUT Informatique", "BUT GEA" (sans FI/FA)
                    
                    // 1. Decoder les entites HTML
                    $formationTitre = html_entity_decode($formationTitre, ENT_QUOTES, 'UTF-8');
                    
                    // 2. Corriger les encodages casses
                    $corrections = [
                        'Carri_res' => 'Carrieres',
                        'carri_res' => 'carrieres',
                        'G_nie' => 'Genie',
                        'g_nie' => 'genie',
                        'R_T' => 'R&T',
                        '_' => ' ',
                    ];
                    foreach ($corrections as $search => $replace) {
                        $formationTitre = str_replace($search, $replace, $formationTitre);
                    }
                    
                    // 3. Exclure les formations d'autres IUT
                    if (preg_match('/IUT\s+(de\s+)?Paris|IUT\s+Sceaux/i', $formationTitre)) {
                        continue;
                    }
                    
                    // 3b. Exclure les formations en alternance et passerelle (gardees pour les scenarios)
                    // alternance, altenance (faute), Apprentissage, Passerelle, FA
                    if (preg_match('/alternance|altenance|Apprentissage|Passerelle|\\bFA\\b/i', $formationTitre)) {
                        continue;
                    }
                    
                    // 4. "Bachelor Universitaire de Technologie" -> "BUT"
                    $formationTitre = preg_replace('/Bachelor\s+Universitaire\s+de\s+Technologie/i', 'BUT', $formationTitre);
                    
                    // 5. Supprimer numeros de niveau (BUT 1, BUT1, etc.)
                    $formationTitre = preg_replace('/\bBUT\s*[123]\b/i', 'BUT', $formationTitre);
                    
                    // 6. Supprimer numeros de semestre (S1-S6)
                    $formationTitre = preg_replace('/\s+S[1-6]\b/i', '', $formationTitre);
                    
                    // 7. Supprimer "PN", "PN 2021", etc.
                    $formationTitre = preg_replace('/\s*\(?PN\s*\d*\s*\.?\)?/i', '', $formationTitre);
                    
                    // 8. Enlever les annees (2020-2029)
                    $formationTitre = preg_replace('/\s+20[2-9]\d(\s|$)/', ' ', $formationTitre);
                    
                    // 9. Normaliser noms longs vers acronymes
                    $normalisations = [
                        '/\bSTID\b/i' => 'SD',
                        '/G[eé]nie\s+[EÉe]lectrique\s+et\s+Informatique\s+Industrielle/i' => 'GEII',
                        '/Carri[eè]res\s+Juridiques/i' => 'CJ',
                        '/Gestion\s+des\s+Entreprises\s+et\s+des?\s+Administrations?/i' => 'GEA',
                        '/R[eé]seaux\s+et\s+T[eé]l[eé]communications/i' => 'R&T',
                        '/Sciences\s+des\s+Donn[eé]es/i' => 'SD',
                    ];
                    foreach ($normalisations as $pattern => $replacement) {
                        $formationTitre = preg_replace($pattern, $replacement, $formationTitre);
                    }
                    
                    // 10. SUPPRIMER FI/FA et toutes les variantes
                    $formationTitre = preg_replace('/\s+(FI|FA)\b/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\s+en\s+(alternance|Apprentissage|classique)/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\s+en\s+FI\s+classique/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\bApprentissage\b/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\bFormation\s+initiale\b/i', '', $formationTitre);
                    
                    // 11. Supprimer les parcours
                    $formationTitre = preg_replace('/\s*[-–]\s*Parcours\s+[A-Z0-9\s]+/i', '', $formationTitre);
                    
                    // 12. Cas speciaux problematiques
                    // "BUT CJ GEA" -> "BUT GEA" (le fichier CJ_GEA est pour GEA, pas CJ)
                    $formationTitre = preg_replace('/\bBUT\s+CJ\s+GEA\b/i', 'BUT GEA', $formationTitre);
                    // "BUT SD INFO" -> garder (Passerelle SD vers INFO)
                    // Mais "BUT SD" seul doit rester "BUT SD"
                    
                    // 13. Supprimer tirets et caracteres speciaux en fin de nom
                    $formationTitre = preg_replace('/\s*[-–_]\s*$/i', '', $formationTitre);
                    
                    // 14. Nettoyage final
                    $formationTitre = preg_replace('/\s+/', ' ', $formationTitre);
                    $formationTitre = trim($formationTitre);

                    $id_formation_bdd = null;
                    if ($formationTitre) {
                        // Vérifier si la formation existe déjà
                        $stmtCheck = $pdo->prepare("SELECT id_formation FROM formation WHERE titre = ? LIMIT 1");
                        $stmtCheck->execute([$formationTitre]);
                        $existingForm = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        if ($existingForm) {
                            $id_formation_bdd = $existingForm['id_formation'];
                        } else {
                            // S'assurer que le département existe
                            $pdo->exec("INSERT IGNORE INTO departement (id_dept, acronyme, nom_complet) VALUES (1, 'IUT', 'IUT Villetaneuse')");
                            
                            // INSÉRER la formation
                            $stmtInsertForm = $pdo->prepare("INSERT INTO formation (id_dept, titre) VALUES (1, ?)");
                            $stmtInsertForm->execute([$formationTitre]);
                            $id_formation_bdd = $pdo->lastInsertId();
                        }
                    }

                    // EXTRACTION: numero de semestre depuis le JSON ou le nom de fichier
                    $numSemestre = $data[0]["semestre"]["ordre"] ?? null;
                    if (!$numSemestre && isset($data[0]["annee"]["ordre"])) {
                         $numSemestre = ($data[0]["annee"]["ordre"] * 2) - 1;
                    }
                    // Fallback: extraire depuis le nom de fichier (BUT1, BUT_2, S2, S4, etc.)
                    if (!$numSemestre) {
                        $filename = basename($file);
                        if (preg_match('/BUT[_\s]?1/i', $filename)) $numSemestre = 1;
                        elseif (preg_match('/BUT[_\s]?2/i', $filename)) $numSemestre = 3;
                        elseif (preg_match('/BUT[_\s]?3/i', $filename)) $numSemestre = 5;
                        elseif (preg_match('/S2\b/i', $filename)) $numSemestre = 2;
                        elseif (preg_match('/S4\b/i', $filename)) $numSemestre = 4;
                        elseif (preg_match('/S6\b/i', $filename)) $numSemestre = 6;
                        else $numSemestre = 1; // Default to S1 if unknown
                    }
                    
                    // EXTRACTION: annee scolaire depuis le JSON ou le nom de fichier
                    $anneeScolaire = $data[0]["annee"]["annee_scolaire"] ?? null;
                    if (!$anneeScolaire) {
                        // Extraire depuis le nom de fichier: decisions_jury_2022_fs_...
                        if (preg_match('/decisions_jury_(\d{4})_/', basename($file), $matchAnnee)) {
                            $anneeScolaire = $matchAnnee[1];
                        }
                    }

                    $sqlInsertSemestreGeneric = "INSERT INTO semestre_instance (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite) 
                                                 VALUES (:idfs, :idf, :annee, :num, :modalite) 
                                                 ON DUPLICATE KEY UPDATE id_formation = VALUES(id_formation), annee_scolaire = VALUES(annee_scolaire), numero_semestre = VALUES(numero_semestre)";
                    $stmtSemestreUpdate = $pdo->prepare($sqlInsertSemestreGeneric);

                    $stmtSemestreUpdate->execute([
                        ":idfs" => $id_formsemestre, ":idf" => $id_formation_bdd, 
                        ":annee" => $anneeScolaire, 
                        ":num" => $numSemestre, ":modalite" => null
                    ]);

                    foreach ($data as $etu) {
                        if (empty($etu["code_nip"])) continue;

                        $sqlInsertEtudiant->execute([":nip" => $etu["code_nip"], ":ine" => $etu["code_ine"] ?? null, ":etud" => $etu["etudid"] ?? null]);
                        
                        $decisionJury = $etu["annee"]["code"] ?? null;
                        $etatInscription = $etu["etat"] ?? null;
                        
                        $sqlInsertInscription->execute([
                            ":nip" => $etu["code_nip"], 
                            ":fs" => $id_formsemestre, 
                            ":jury" => $decisionJury,  // ADM, RED, NAR, PASD, DEF, etc.
                            ":annee"=> $etu["annee"]["ordre"] ?? null, 
                            ":etat" => $etatInscription,  // I ou D
                            ":pct" => $etu["nb_competences"] ?? null, 
                            ":isapc"=> ($etu["is_apc"] ?? false) ? 1 : 0, 
                            ":maj" => date("Y-m-d H:i:s")
                        ]);
                        $id_inscription = $pdo->lastInsertId();

                        if (!empty($etu["rcues"])) {
                            $num = 1;
                            foreach ($etu["rcues"] as $rc) {
                                $sqlInsertCompetence->execute([":insc" => $id_inscription, ":num" => $num, ":code" => $rc["code"], ":moy" => $rc["moy"]]);
                                $num++;
                            }
                        }
                    }
                    $count++;
                }

                // Message final
                if ($has_error) {
                     $message_sync = implode("<br>", $steps_log) . "<br> Synchronisation partielle ou echouee.";
                     $message_type = "error";
                } else {
                     $message_sync = "Données ScoDoc synchronisées avec succès.";
                     $message_type = "success";
                }
            }
        }
    } catch (Exception $e) {
        $message_sync = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }

    // Sauvegarde en session
    if ($message_sync) {
        $_SESSION['sync_message'] = $message_sync;
        $_SESSION['sync_type'] = $message_type;
    }
    
    // Redirection
    header('Location: admin.php');
    exit;

} else {
    // Accès direct non autorisé
    header('Location: admin.php');
    exit;
}
