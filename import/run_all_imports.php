<?php
/**
 * Orchestrateur d'import - Appelle tous les scripts d'import dans le bon ordre
 * Peut être appelé depuis admin.php ou exécuté directement
 */

// Retourner les résultats au lieu de les afficher si appelé comme fonction
function runAllImports($pdo, $jsonFolder) {
    $results = [
        'success' => true,
        'steps' => [],
        'errors' => []
    ];

    // Capturer la sortie des scripts
    ob_start();

    try {
        $pdo->beginTransaction(); // Début de la transaction

        // ETAPE 1: Import des départements (si le fichier existe)
        $deptFile = $jsonFolder . '/departements.json';
        if (file_exists($deptFile)) {
            $result = importDepartements($pdo, $deptFile);
            $results['steps'][] = "Départements: " . $result['count'] . " traités";
        } else {
            // Créer un département par défaut
            $pdo->exec("INSERT IGNORE INTO Departement (id_dept, acronyme, nom_complet) VALUES (1, 'IUT', 'IUT Villetaneuse')");
            $results['steps'][] = "Département par défaut créé";
        }

        // ETAPE 2: Import des formations (si le fichier existe)
        $formFile = $jsonFolder . '/formations.json';
        if (file_exists($formFile)) {
            $result = importFormations($pdo, $formFile);
            $results['steps'][] = "Formations: " . $result['count'] . " traitées";
        }

        // ETAPE 3: Import des semestres (si fichiers formsemestres existent)
        $semFiles = glob($jsonFolder . '/formsemestres_*.json');
        if (!empty($semFiles)) {
            $result = importSemestres($pdo, $semFiles);
            $results['steps'][] = "Semestres: " . $result['count'] . " traités";
        }

        // ETAPE 4: Import depuis decisions_jury (formations, semestres, étudiants, inscriptions)
        $decisionFiles = glob($jsonFolder . '/decisions_jury_*.json');
        if (empty($decisionFiles)) {
            // Chercher dans les sous-dossiers
            $decisionFiles = glob($jsonFolder . '/**/decisions_jury_*.json');
        }

        if (!empty($decisionFiles)) {
            // Import des formations et semestres depuis decisions_jury
            $result = importFormationsFromDecisions($pdo, $decisionFiles);
            $results['steps'][] = "Formations (depuis JSON): " . $result['formations'] . " | Semestres: " . $result['semestres'];

            // Import des étudiants
            $result = importEtudiants($pdo, $decisionFiles);
            $results['steps'][] = "Étudiants: " . $result['count'] . " traités";

            // Import des inscriptions
            $result = importInscriptions($pdo, $decisionFiles);
            $results['steps'][] = "Inscriptions: " . $result['count'] . " traitées";

            // Import des résultats de compétences
            $result = importResultatsCompetences($pdo, $decisionFiles);
            $results['steps'][] = "Résultats compétences: " . $result['count'] . " traités";
        } else {
            $results['errors'][] = "Aucun fichier decisions_jury_*.json trouvé";
            $results['success'] = false;
        }

        $pdo->commit(); // Validation des changements uniquement si tout est OK

    } catch (Exception $e) {
        $pdo->rollBack(); // Annulation en cas d'erreur
        $results['success'] = false;
        $results['errors'][] = $e->getMessage();
    }

    ob_end_clean();

    return $results;
}

/**
 * Normalise le titre d'une formation (supprime FI/FA, convertit acronymes, etc.)
 */
function normaliserFormation($formationTitre) {
    // 1. Decoder les entites HTML
    $formationTitre = html_entity_decode($formationTitre, ENT_QUOTES, 'UTF-8');

    // 2. Corriger les encodages cassés
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
        return null;
    }

    // 3b. Exclure les formations en alternance et passerelle
    if (preg_match('/alternance|altenance|Apprentissage|Passerelle|\bFA\b/i', $formationTitre)) {
        return null;
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
    $formationTitre = preg_replace('/\bBUT\s+CJ\s+GEA\b/i', 'BUT GEA', $formationTitre);

    // 13. Supprimer tirets et caracteres speciaux en fin de nom
    $formationTitre = preg_replace('/\s*[-–_]\s*$/i', '', $formationTitre);

    // 14. Nettoyage final
    $formationTitre = preg_replace('/\s+/', ' ', $formationTitre);
    $formationTitre = trim($formationTitre);

    return $formationTitre;
}

/**
 * Import des formations et semestres depuis les fichiers decisions_jury
 */
function importFormationsFromDecisions($pdo, $files) {
    $formationsCount = 0;
    $semestresCount = 0;

    $sqlCheckFormation = "SELECT id_formation FROM Formation WHERE titre = ? LIMIT 1";
    $stmtCheckFormation = $pdo->prepare($sqlCheckFormation);

    $sqlInsertFormation = "INSERT INTO Formation (id_dept, titre) VALUES (1, ?)";
    $stmtInsertFormation = $pdo->prepare($sqlInsertFormation);

    $sqlInsertSemestre = "INSERT INTO Semestre_Instance (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite)
                          VALUES (:idfs, :idf, :annee, :num, :modalite)
                          ON DUPLICATE KEY UPDATE id_formation = VALUES(id_formation), annee_scolaire = VALUES(annee_scolaire), numero_semestre = VALUES(numero_semestre)";
    $stmtInsertSemestre = $pdo->prepare($sqlInsertSemestre);

    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data)) continue;

        // Extraire id_formsemestre du nom de fichier
        preg_match('/fs_(\d+)/', basename($file), $m);
        $id_formsemestre = $m[1] ?? null;
        if (!$id_formsemestre) continue;

        // RECUPERATION DU TITRE DE FORMATION
        $formationTitre = null;

        // PRIORITE 1: Utiliser le titre du JSON
        if (isset($data[0]["formation"]["titre"]) && !empty($data[0]["formation"]["titre"])) {
            $formationTitre = trim($data[0]["formation"]["titre"]);
        }
        // PRIORITE 2: Fallback sur le nom de fichier
        elseif (preg_match('/fs_\d+_(.*?)\.json$/i', basename($file), $matches)) {
            $formationTitre = $matches[1];
        } else {
            $formationTitre = "Formation Inconnue";
        }

        // Normaliser le titre
        $formationTitre = normaliserFormation($formationTitre);
        if ($formationTitre === null) continue; // Formation exclue

        // Vérifier/Créer la formation
        $id_formation_bdd = null;
        if ($formationTitre) {
            $stmtCheckFormation->execute([$formationTitre]);
            $existingForm = $stmtCheckFormation->fetch(PDO::FETCH_ASSOC);

            if ($existingForm) {
                $id_formation_bdd = $existingForm['id_formation'];
            } else {
                $stmtInsertFormation->execute([$formationTitre]);
                $id_formation_bdd = $pdo->lastInsertId();
                $formationsCount++;
            }
        }

        // EXTRACTION: numero de semestre
        $numSemestre = $data[0]["semestre"]["ordre"] ?? null;
        if (!$numSemestre && isset($data[0]["annee"]["ordre"])) {
            $numSemestre = ($data[0]["annee"]["ordre"] * 2) - 1;
        }
        // Fallback: extraire depuis le nom de fichier
        if (!$numSemestre) {
            $filename = basename($file);
            if (preg_match('/BUT[_\s]?1/i', $filename)) $numSemestre = 1;
            elseif (preg_match('/BUT[_\s]?2/i', $filename)) $numSemestre = 3;
            elseif (preg_match('/BUT[_\s]?3/i', $filename)) $numSemestre = 5;
            elseif (preg_match('/S2\b/i', $filename)) $numSemestre = 2;
            elseif (preg_match('/S4\b/i', $filename)) $numSemestre = 4;
            elseif (preg_match('/S6\b/i', $filename)) $numSemestre = 6;
            else $numSemestre = 1;
        }

        // EXTRACTION: annee scolaire
        $anneeScolaire = $data[0]["annee"]["annee_scolaire"] ?? null;
        if (!$anneeScolaire) {
            if (preg_match('/decisions_jury_(\d{4})_/', basename($file), $matchAnnee)) {
                $anneeScolaire = $matchAnnee[1];
            }
        }

        // Insérer le semestre
        $stmtInsertSemestre->execute([
            ":idfs" => $id_formsemestre,
            ":idf" => $id_formation_bdd,
            ":annee" => $anneeScolaire,
            ":num" => $numSemestre,
            ":modalite" => null
        ]);
        $semestresCount++;
    }

    return ['formations' => $formationsCount, 'semestres' => $semestresCount];
}

/**
 * Import des départements
 */
function importDepartements($pdo, $jsonPath) {
    $jsonContent = file_get_contents($jsonPath);
    $departements = json_decode($jsonContent, true);

    if ($departements === null) {
        throw new Exception("Erreur de décodage JSON départements: " . json_last_error_msg());
    }

    $sql = "INSERT INTO Departement (id_dept, acronyme, nom_complet)
            VALUES (:id_dept, :acronyme, :nom_complet)
            ON DUPLICATE KEY UPDATE
                acronyme = VALUES(acronyme),
                nom_complet = VALUES(nom_complet)";

    $stmt = $pdo->prepare($sql);
    $count = 0;

    foreach ($departements as $dept) {
        $stmt->execute([
            ':id_dept'     => $dept['id'],
            ':acronyme'    => $dept['acronym'] ?? $dept['acronyme'] ?? '',
            ':nom_complet' => $dept['dept_name'] ?? $dept['nom_complet'] ?? '',
        ]);
        $count++;
    }

    return ['count' => $count];
}

/**
 * Import des formations
 */
function importFormations($pdo, $jsonPath) {
    $jsonContent = file_get_contents($jsonPath);
    $formations = json_decode($jsonContent, true);

    if ($formations === null) {
        throw new Exception("Erreur de décodage JSON formations: " . json_last_error_msg());
    }

    $sql = "INSERT INTO Formation (id_formation, id_dept, code_scodoc, titre)
            VALUES (:id_formation, :id_dept, :code_scodoc, :titre)
            ON DUPLICATE KEY UPDATE
                id_dept = VALUES(id_dept),
                code_scodoc = VALUES(code_scodoc),
                titre = VALUES(titre)";

    $stmt = $pdo->prepare($sql);
    $count = 0;

    foreach ($formations as $form) {
        $idFormation = $form['id'] ?? $form['id_formation'];
        $idDept = $form['dept_id'] ?? $form['id_dept'] ?? 1;
        $codeScodoc = $form['formation_code'] ?? $form['code_scodoc'] ?? '';
        $titre = !empty($form['titre_officiel']) ? $form['titre_officiel'] : ($form['titre'] ?? '');

        $stmt->execute([
            ':id_formation' => $idFormation,
            ':id_dept'      => $idDept,
            ':code_scodoc'  => $codeScodoc,
            ':titre'        => $titre,
        ]);
        $count++;
    }

    return ['count' => $count];
}

/**
 * Import des semestres
 */
function importSemestres($pdo, $files) {
    $sql = "INSERT INTO Semestre_Instance (
                id_formsemestre, id_formation, annee_scolaire,
                numero_semestre, modalite, date_debut, date_fin
            ) VALUES (
                :id_formsemestre, :id_formation, :annee_scolaire,
                :numero_semestre, :modalite, :date_debut, :date_fin
            )
            ON DUPLICATE KEY UPDATE
                id_formation = VALUES(id_formation),
                annee_scolaire = VALUES(annee_scolaire),
                numero_semestre = VALUES(numero_semestre),
                modalite = VALUES(modalite),
                date_debut = VALUES(date_debut),
                date_fin = VALUES(date_fin)";

    $stmt = $pdo->prepare($sql);
    $count = 0;

    foreach ($files as $filepath) {
        $jsonContent = file_get_contents($filepath);
        $data = json_decode($jsonContent, true);

        if ($data === null) continue;

        foreach ($data as $sem) {
            $idFormsemestre = $sem['formsemestre_id'] ?? $sem['id'] ?? null;
            if ($idFormsemestre === null) continue;

            $stmt->execute([
                ':id_formsemestre' => $idFormsemestre,
                ':id_formation'    => $sem['formation_id'] ?? null,
                ':annee_scolaire'  => $sem['annee_scolaire'] ?? null,
                ':numero_semestre' => $sem['semestre_id'] ?? null,
                ':modalite'        => $sem['modalite'] ?? null,
                ':date_debut'      => $sem['date_debut_iso'] ?? null,
                ':date_fin'        => $sem['date_fin_iso'] ?? null,
            ]);
            $count++;
        }
    }

    return ['count' => $count];
}

/**
 * Import des étudiants depuis les fichiers decisions_jury
 */
function importEtudiants($pdo, $files) {
    $sql = "INSERT INTO Etudiant (code_nip, code_ine, etudid_scodoc)
            VALUES (:code_nip, :code_ine, :etudid_scodoc)
            ON DUPLICATE KEY UPDATE
                code_ine = VALUES(code_ine),
                etudid_scodoc = VALUES(etudid_scodoc)";

    $stmt = $pdo->prepare($sql);
    $seenNip = [];
    $count = 0;

    foreach ($files as $filepath) {
        $jsonContent = file_get_contents($filepath);
        $data = json_decode($jsonContent, true);

        if ($data === null || !is_array($data)) continue;

        foreach ($data as $etudiant) {
            $codeNip = $etudiant['code_nip'] ?? null;
            if (empty($codeNip) || isset($seenNip[$codeNip])) continue;

            $seenNip[$codeNip] = true;

            $stmt->execute([
                ':code_nip'      => $codeNip,
                ':code_ine'      => $etudiant['code_ine'] ?? null,
                ':etudid_scodoc' => $etudiant['etudid'] ?? null,
            ]);
            $count++;
        }
    }

    return ['count' => $count];
}

/**
 * Import des inscriptions
 */
function importInscriptions($pdo, $files) {
    $sql = "INSERT IGNORE INTO Inscription (
                code_nip, id_formsemestre, decision_jury, decision_annee,
                etat_inscription, pct_competences, is_apc, date_maj
            ) VALUES (
                :code_nip, :id_formsemestre, :decision_jury, :decision_annee,
                :etat_inscription, :pct_competences, :is_apc, :date_maj
            )";

    $stmt = $pdo->prepare($sql);
    $dateMaj = date('Y-m-d H:i:s');
    $count = 0;

    foreach ($files as $filepath) {
        $filename = basename($filepath);

        // Extraire l'id_formsemestre du nom de fichier
        if (!preg_match('/fs_(\d+)/i', $filename, $matches)) continue;
        $formsemestreId = (int) $matches[1];

        $jsonContent = file_get_contents($filepath);
        $data = json_decode($jsonContent, true);

        if ($data === null || !is_array($data)) continue;

        foreach ($data as $etudiant) {
            $codeNip = $etudiant['code_nip'] ?? null;
            if (empty($codeNip)) continue;

            // decision_jury depuis semestre.code ou annee.code
            $decisionJury = null;
            if (!empty($etudiant['semestre']) && isset($etudiant['semestre']['code'])) {
                $decisionJury = $etudiant['semestre']['code'];
            } elseif (!empty($etudiant['annee']) && isset($etudiant['annee']['code'])) {
                $decisionJury = $etudiant['annee']['code'];
            }

            $stmt->execute([
                ':code_nip'        => $codeNip,
                ':id_formsemestre' => $formsemestreId,
                ':decision_jury'   => $decisionJury,
                ':decision_annee'  => $etudiant['annee']['ordre'] ?? null,
                ':etat_inscription' => $etudiant['etat'] ?? null,
                ':pct_competences' => $etudiant['nb_competences'] ?? null,
                ':is_apc'          => isset($etudiant['is_apc']) ? ($etudiant['is_apc'] ? 1 : 0) : null,
                ':date_maj'        => $dateMaj,
            ]);
            $count++;
        }
    }

    return ['count' => $count];
}

/**
 * Import des résultats de compétences
 */
function importResultatsCompetences($pdo, $files) {
    // Requête pour retrouver l'id_inscription
    $sqlFindInsc = "SELECT id_inscription FROM Inscription
                    WHERE code_nip = :code_nip AND id_formsemestre = :id_formsemestre
                    LIMIT 1";
    $stmtFindInsc = $pdo->prepare($sqlFindInsc);

    $sqlInsert = "INSERT IGNORE INTO Resultat_Competence (
                      id_inscription, numero_competence, code_decision, moyenne
                  ) VALUES (
                      :id_inscription, :numero_competence, :code_decision, :moyenne
                  )";
    $stmtInsert = $pdo->prepare($sqlInsert);

    $count = 0;

    foreach ($files as $filepath) {
        $filename = basename($filepath);

        if (!preg_match('/fs_(\d+)/i', $filename, $matches)) continue;
        $idFormsemestre = (int) $matches[1];

        $jsonContent = file_get_contents($filepath);
        $data = json_decode($jsonContent, true);

        if ($data === null || !is_array($data)) continue;

        foreach ($data as $etudiant) {
            $codeNip = $etudiant['code_nip'] ?? null;
            if (empty($codeNip)) continue;

            // Retrouver l'id_inscription
            $stmtFindInsc->execute([
                ':code_nip'        => $codeNip,
                ':id_formsemestre' => $idFormsemestre,
            ]);
            $idInscription = $stmtFindInsc->fetchColumn();

            if ($idInscription === false) continue;

            if (empty($etudiant['rcues']) || !is_array($etudiant['rcues'])) continue;

            $numero = 1;
            foreach ($etudiant['rcues'] as $rcue) {
                $stmtInsert->execute([
                    ':id_inscription'    => $idInscription,
                    ':numero_competence' => $numero,
                    ':code_decision'     => $rcue['code'] ?? null,
                    ':moyenne'           => $rcue['moy'] ?? null,
                ]);
                $count++;
                $numero++;
            }
        }
    }

    return ['count' => $count];
}

// Si exécuté directement (pas inclus)
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    require_once dirname(__DIR__) . '/config.php';

    $jsonFolder = __DIR__ . '/SAE_json';
    if (!is_dir($jsonFolder)) {
        $jsonFolder = dirname(__DIR__) . '/uploads/SAE_json';
    }

    echo "Démarrage de l'import...\n";
    echo "Dossier JSON: $jsonFolder\n\n";

    $results = runAllImports($pdo, $jsonFolder);

    echo "=== Résultats ===\n";
    foreach ($results['steps'] as $step) {
        echo "✓ $step\n";
    }

    if (!empty($results['errors'])) {
        echo "\n=== Erreurs ===\n";
        foreach ($results['errors'] as $error) {
            echo "✗ $error\n";
        }
    }

    echo "\nStatut: " . ($results['success'] ? "Succès" : "Échec") . "\n";
}
