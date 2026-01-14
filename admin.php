<?php
session_start();
require_once 'config.php';

// Vérification authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html');
    exit;
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$message_sync = "";
$message_type = "";

// Recuperer le message de la session (POST-Redirect-GET pattern)
if (isset($_SESSION['sync_message'])) {
    $message_sync = $_SESSION['sync_message'];
    $message_type = $_SESSION['sync_type'] ?? 'success';
    unset($_SESSION['sync_message']);
    unset($_SESSION['sync_type']);
}

// Connexion PDO pour les opérations admin
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $message_sync = "Erreur connexion BDD: " . $e->getMessage();
    $message_type = "error";
}

// GESTIONNAIRE : Ajout Mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mapping'])) {
    $code = trim($_POST['mapping_code'] ?? '');
    $label = trim($_POST['mapping_label'] ?? '');
    if ($code && $label) {
        try {
            // Créer la table si elle n'existe pas
            $pdo->exec("CREATE TABLE IF NOT EXISTS mapping_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code_scodoc VARCHAR(100) UNIQUE,
                libelle_graphique VARCHAR(255)
            )");
            $stmt = $pdo->prepare("INSERT INTO mapping_codes (code_scodoc, libelle_graphique) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE libelle_graphique = VALUES(libelle_graphique)");
            $stmt->execute([$code, $label]);
            $message_sync = "Mapping ajouté : $code → $label";
            $message_type = "success";
        } catch (Exception $e) {
            $message_sync = "Erreur ajout mapping : " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// GESTIONNAIRE : Ajout Scénario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_scenario'])) {
    $source = trim($_POST['scenario_source'] ?? '');
    $target = trim($_POST['scenario_target'] ?? '');
    $type = trim($_POST['scenario_type'] ?? '');
    if ($source && $target && $type) {
        try {
            // Créer la table si elle n'existe pas
            $pdo->exec("CREATE TABLE IF NOT EXISTS scenario_correspondance (
                id_scenario INT AUTO_INCREMENT PRIMARY KEY,
                formation_source VARCHAR(255),
                formation_cible VARCHAR(255),
                type_flux VARCHAR(50),
                UNIQUE KEY unique_scenario (formation_source, formation_cible)
            )");
            $stmt = $pdo->prepare("INSERT INTO scenario_correspondance (formation_source, formation_cible, type_flux) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE type_flux = VALUES(type_flux)");
            $stmt->execute([$source, $target, $type]);
            $message_sync = "Scénario ajouté : $source → $target [$type]";
            $message_type = "success";
        } catch (Exception $e) {
            $message_sync = "Erreur ajout scénario : " . $e->getMessage();
            $message_type = "error";
        }
    }
}


// LOGIQUE UNIQUE (Auto-Dézip + Import)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sync'])) {
    ini_set("max_execution_time", 300);
    $has_error = false;
    $steps_log = [];

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
        $folder = __DIR__ . "/uploads/saejson/";
        if (is_dir($folder . "SAE_json")) $folder = $folder . "SAE_json/";

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
                    
                    // 10. SUPPRIMER FI/FA et toutes les variantes (selon image prof)
                    $formationTitre = preg_replace('/\s+(FI|FA)\b/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\s+en\s+(alternance|Apprentissage|FI\s+classique|Formation\s+initiale)/i', '', $formationTitre);
                    $formationTitre = preg_replace('/\bApprentissage\b/i', '', $formationTitre);
                    
                    // 11. Supprimer les parcours (pour simplifier)
                    $formationTitre = preg_replace('/\s*[-–]\s*Parcours\s+[A-Z0-9\s]+/i', '', $formationTitre);
                    
                    // 12. Supprimer Passerelle en debut (devient formation separee)
                    // Garder "BUT Passerelle SD INFO" comme cas special
                    
                    // 13. Nettoyage final
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

                    $numSemestre = $data[0]["semestre"]["ordre"] ?? null;
                    if (!$numSemestre && isset($data[0]["annee"]["ordre"])) {
                         $numSemestre = ($data[0]["annee"]["ordre"] * 2) - 1;
                    }

                    $sqlInsertSemestreGeneric = "INSERT INTO semestre_instance (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite) 
                                                 VALUES (:idfs, :idf, :annee, :num, :modalite) 
                                                 ON DUPLICATE KEY UPDATE id_formation = VALUES(id_formation), annee_scolaire = VALUES(annee_scolaire), numero_semestre = VALUES(numero_semestre)";
                    $stmtSemestreUpdate = $pdo->prepare($sqlInsertSemestreGeneric);

                    $stmtSemestreUpdate->execute([
                        ":idfs" => $id_formsemestre, ":idf" => $id_formation_bdd, 
                        ":annee" => $data[0]["annee"]["annee_scolaire"] ?? null, 
                        ":num" => $numSemestre, ":modalite" => null
                    ]);

                    foreach ($data as $etu) {
                        if (empty($etu["code_nip"])) continue;

                        $sqlInsertEtudiant->execute([":nip" => $etu["code_nip"], ":ine" => $etu["code_ine"] ?? null, ":etud" => $etu["etudid"] ?? null]);
                        
                        // CORRECTION: Utiliser annee.code pour la décision de jury (ADM, RED, NAR, etc.)
                        // et etat pour l'état d'inscription (I=Inscrit, D=Démission)
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
                     $_SESSION['sync_message'] = implode("<br>", $steps_log) . "<br> Synchronisation partielle ou echouee.";
                     $_SESSION['sync_type'] = "error";
                } else {
                     $_SESSION['sync_message'] = "Donnees ScoDoc synchronisees avec succes ($count fichiers traites).";
                     $_SESSION['sync_type'] = "success";
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['sync_message'] = "Erreur : " . $e->getMessage();
        $_SESSION['sync_type'] = "error";
    }
    
    // Redirection pour eviter la resynchronisation au refresh (POST-Redirect-GET)
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - MNEMOSYNE</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/logo.png?v=3">
    <link rel="stylesheet" href="styles.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        /* CSS Admin Spécifique pour Loader Inline */
        .btn-sync-header {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        .btn-sync-header:hover {
            background: white;
            color: #1e3a5f;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .admin-alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Loader Inline */
        #inline-loader {
            display: none; /* Caché par défaut */
            background: #e3f2fd;
            color: #0d47a1;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 2rem;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            font-weight: 600;
            border: 1px solid #90caf9;
        }
        .inline-spinner {
            width: 24px; height: 24px;
            border: 3px solid #bbdefb;
            border-top: 3px solid #0d47a1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <img src="assets/logo.png" alt="Logo Mnémosyne" class="logo-image">
                    <div class="logo-text">
                        <h1 class="logo-title">MNEMOSYNE</h1>
                        <p class="logo-subtitle">Administration</p>
                    </div>
                </div>

                <div class="header-center" style="display: flex; gap: 0.5rem;">
                    <form method="POST" class="sync-form-header" onsubmit="document.getElementById('inline-loader').style.display='flex'">
                        <button type="submit" name="run_sync" class="btn-sync-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9"/>
                            </svg>
                            Synchroniser
                        </button>
                    </form>

                </div>

                <nav class="nav-buttons">
                    <button class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="currentColor"/>
                        </svg>
                    </button>
                    <a href="admin.php?logout=true" class="btn-nav btn-logout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Déconnexion
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-section">
        <div class="container">
             <div class="hero-content">
                <div class="hero-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                         <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <h2 class="hero-title">Suivi des Cohortes</h2>
                <p class="hero-subtitle">IUT de Villetaneuse</p>
                <p class="hero-quote">"Garder la mémoire, éclairer les parcours"</p>
            </div>

            <!-- Loader Inline (Juste au dessus du formulaire) -->
            <div id="inline-loader">
                <div class="inline-spinner"></div>
                <span>Récupération des données en cours, veuillez patienter...</span>
            </div>

            <!-- ALERT DE FEEDBACK -->
            <?php if (!empty($message_sync)): ?>
                <div class="admin-alert <?php echo ($message_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                    <?php if($message_type === 'success'): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php endif; ?>
                    <div><?php echo $message_sync; ?></div>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3 class="form-title">Accès aux parcours des étudiants</h3>
                <form class="search-form" id="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <select id="formation" class="form-select">
                                <option value="">Choisir une formation...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select id="annee" class="form-select">
                                <option value="">Choisir une année...</option>
                            </select>
                        </div>
                    </div>

                    <div class="quick-filters">
                        <span class="quick-label">Accès rapide :</span>
                        <button type="button" class="chip" onclick="quickSelect('BUT Informatique')">Informatique</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT GEA')">GEA</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT TC')">TC</button>
                    </div>

                    <button type="submit" class="btn-submit">
                        Voir les parcours
                        <span class="arrow">→</span>
                    </button>
                </form>
            </div>

            <div id="chart-wrapper" style="position: relative;">
                <div id="chart-loader" class="chart-loader" style="display: none;">
                    <div class="spinner"></div>
                    <p>Chargement des données...</p>
                </div>
                <div id="sankey_chart" class="chart-box">
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1dce5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                            <path d="M2 12h20M2 12l5-5m-5 5l5 5"/>
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <h4>En attente de sélection</h4>
                        <p>Sélectionnez une formation ci-dessus pour visualiser les flux.</p>
                    </div>
                </div>
            </div>

            <div class="results-section" id="results-section" style="display:none; margin-top: 3rem;">
                <div class="results-header">
                    <h3 class="results-title">Bilan des compétences & Décisions de jury</h3>
                    <p class="results-subtitle" id="stats-subtitle">Formation : - • Année : -</p>
                </div>
                
                <!-- Status Cards -->
                <div class="status-cards">
                    <div class="status-card status-validé">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line"><span class="status-code">ADM</span><span class="status-label">Diplômé</span></div>
                                </div>
                            </div>
                            <div class="status-stats"><div class="status-number" id="count-valide">0</div><div class="status-percent" id="percent-valide">0%</div></div>
                        </div>
                    </div>
                    <div class="status-card status-partiel">
                         <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line"><span class="status-code">PASS</span><span class="status-label">En cours</span></div>
                                </div>
                            </div>
                            <div class="status-stats"><div class="status-number" id="count-partiel">0</div><div class="status-percent" id="percent-partiel">0%</div></div>
                        </div>
                    </div>
                    <div class="status-card status-redoublement">
                         <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line"><span class="status-code">RED/AJ</span><span class="status-label">Redoublement</span></div>
                                </div>
                            </div>
                            <div class="status-stats"><div class="status-number" id="count-red">0</div><div class="status-percent" id="percent-red">0%</div></div>
                        </div>
                    </div>
                    <div class="status-card status-abandon">
                         <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line"><span class="status-code">NAR/DEF</span><span class="status-label">Abandon/Réo.</span></div>
                                </div>
                            </div>
                            <div class="status-stats"><div class="status-number" id="count-abd">0</div><div class="status-percent" id="percent-abd">0%</div></div>
                        </div>
                    </div>
                </div>

                <div class="total-section">
                    <span class="total-label">Total des étudiants</span>
                    <span class="total-number" id="total-students">0</span>
                </div>

                <div class="info-section">
                    <h4 class="info-title">Informations sur le diagramme</h4>
                    <p class="info-text"><strong>Entrées (Gauche) :</strong> Sources d'arrivée des étudiants (Parcoursup, Redoublants, Passerelles).</p>
                    <p class="info-text"><strong>Flux (Centre) :</strong> Parcours BUT1 ⇨ BUT2 ⇨ BUT3. L'épaisseur représente le nombre d'étudiants.</p>
                    <p class="info-text"><strong>Sorties (Droite) :</strong> Diplômés, Abandons, Réorientations.</p>
                    <p class="info-note">💡 <strong>Astuce :</strong> Survolez les flux pour voir le nombre exact d'étudiants.</p>
                </div>
            </div>

            <div class="features" style="margin-top: 3rem;">
                <div class="feature-card">
                    <h4 class="feature-title">Suivi en temps réel</h4>
                    <p class="feature-text">Visualisation dynamique des parcours étudiants.</p>
                </div>
                <div class="feature-card">
                    <h4 class="feature-title">Données actualisées</h4>
                    <p class="feature-text">Synchronisation avec les données officielles.</p>
                </div>
                <div class="feature-card">
                    <h4 class="feature-title">Statistiques avancées</h4>
                    <p class="feature-text">Analyses des taux de réussite et d'échec.</p>
                </div>
            </div>
        </div>

        <!-- SECTION ADMINISTRATION AVANCÉE -->
        <div class="container" style="margin-top: 3rem;">
            <h2 style="color: var(--heading-color, #1a3a5c); margin-bottom: 2rem; border-bottom: 2px solid #2d5a8c; padding-bottom: 0.5rem;">
                Configuration Avancée
            </h2>
            
            <!-- Interface Mapping -->
            <div class="config-card">
                <h3 style="color: var(--heading-color, #1a3a5c); margin-bottom: 1rem;">Mapping des Codes ScoDoc</h3>
                <p style="color: var(--text-muted, #666); margin-bottom: 1.5rem;">Associez les codes techniques ScoDoc à des libellés lisibles pour les graphiques.</p>
                
                <form method="POST" style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr auto;">
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Code ScoDoc</label>
                        <input type="text" name="mapping_code" placeholder="Ex: B1-INFO-FI" class="config-input">
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Libellé Affiché</label>
                        <input type="text" name="mapping_label" placeholder="Ex: BUT1 Informatique FI" class="config-input">
                    </div>
                    <button type="submit" name="add_mapping" 
                            style="background: #28a745; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; align-self: end; font-weight: 600;">
                        + Ajouter
                    </button>
                </form>
                
                <div class="config-hint">
                    <p>Les mappings seront utilises pour rendre les noms de formation plus lisibles dans les graphiques.</p>
                </div>
            </div>
            
            <!-- Interface Scénarios -->
            <div class="config-card">
                <h3 style="color: var(--heading-color, #1a3a5c); margin-bottom: 1rem;">Règles de Scénarios (Flux)</h3>
                <p style="color: var(--text-muted, #666); margin-bottom: 1.5rem;">Définissez comment les transitions entre formations sont classifiées dans le Sankey.</p>
                
                <form method="POST" style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr 1fr auto;">
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Formation Source</label>
                        <select name="scenario_source" class="config-input">
                            <option value="">Sélectionner...</option>
                            <?php
                            try {
                                $stmtForms = $pdo->query("SELECT DISTINCT titre FROM formation ORDER BY titre");
                                while ($row = $stmtForms->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"" . htmlspecialchars($row['titre']) . "\">" . htmlspecialchars($row['titre']) . "</option>";
                                }
                            } catch (Exception $e) {}
                            ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Formation Cible</label>
                        <select name="scenario_target" class="config-input">
                            <option value="">Sélectionner...</option>
                            <?php
                            try {
                                $stmtForms2 = $pdo->query("SELECT DISTINCT titre FROM formation ORDER BY titre");
                                while ($row = $stmtForms2->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"" . htmlspecialchars($row['titre']) . "\">" . htmlspecialchars($row['titre']) . "</option>";
                                }
                            } catch (Exception $e) {}
                            ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Type de Flux</label>
                        <select name="scenario_type" class="config-input">
                            <option value="passage">Passage Normal</option>
                            <option value="redoublement">Redoublement</option>
                            <option value="passerelle">Passerelle</option>
                            <option value="reorientation">Réorientation</option>
                            <option value="abandon">Abandon</option>
                        </select>
                    </div>
                    <button type="submit" name="add_scenario" 
                            style="background: #17a2b8; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; align-self: end; font-weight: 600;">
                        + Ajouter
                    </button>
                </form>
                
                <div class="config-example">
                    <strong>Exemple :</strong> Si un etudiant passe de "BUT1 SD" a "BUT2 Passerelle INFO", classifiez ce flux comme "Passerelle" pour le voir correctement dans le Sankey.
                </div>
            </div>
        </div>
    </main>
    <footer class="footer">
        <div class="container">
            <p class="footer-copyright">© 2025 Mnémosyne</p>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
