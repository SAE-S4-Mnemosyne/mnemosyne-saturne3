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
            // Vérifier si la table existe avec les bonnes colonnes
            $checkCols = $pdo->query("SHOW COLUMNS FROM scenario_correspondance LIKE 'formation_source'");
            if ($checkCols->rowCount() == 0) {
                // L'ancienne table utilise des IDs, on la supprime et recrée
                $pdo->exec("DROP TABLE IF EXISTS scenario_correspondance");
            }
            
            // Créer la table avec les colonnes texte (noms de formations)
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

// GESTIONNAIRE : Suppression Mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mapping'])) {
    $id = (int)($_POST['delete_mapping_id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM mapping_codes WHERE id = ?");
            $stmt->execute([$id]);
            $message_sync = "Mapping supprimé";
            $message_type = "success";
        } catch (Exception $e) {
            $message_sync = "Erreur suppression mapping";
            $message_type = "error";
        }
    }
}

// GESTIONNAIRE : Suppression Scénario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scenario'])) {
    $id = (int)($_POST['delete_scenario_id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM scenario_correspondance WHERE id_scenario = ?");
            $stmt->execute([$id]);
            $message_sync = "Scénario supprimé";
            $message_type = "success";
        } catch (Exception $e) {
            $message_sync = "Erreur suppression scénario";
            $message_type = "error";
        }
    }
}


// LOGIQUE UNIQUE (Auto-Dezip + Import via orchestrateur)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sync'])) {
    ini_set("max_execution_time", 300);

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
            } else {
                $_SESSION['sync_message'] = "Erreur technique (Zip corrompu).";
                $_SESSION['sync_type'] = "error";
                header('Location: admin.php');
                exit;
            }
        }

        // ETAPE 2 : Déterminer le dossier JSON
        $folder = __DIR__ . "/uploads/SAE_json/";
        if (!is_dir($folder)) {
            $folder = __DIR__ . "/uploads/saejson/";
            if (is_dir($folder . "SAE_json")) $folder = $folder . "SAE_json/";
        }

        // Vérifier aussi le dossier import/SAE_json
        if (!is_dir($folder)) {
            $folder = __DIR__ . "/import/SAE_json/";
        }

        if (!is_dir($folder)) {
            $_SESSION['sync_message'] = "Dossier de données introuvable.";
            $_SESSION['sync_type'] = "error";
            header('Location: admin.php');
            exit;
        }

        // ETAPE 3 : Appeler l'orchestrateur d'import
        require_once __DIR__ . '/import/run_all_imports.php';

        $results = runAllImports($pdo, $folder);

        // Message final
        if ($results['success']) {
            $stepsMsg = implode(" | ", $results['steps']);
            $_SESSION['sync_message'] = "Données ScoDoc synchronisées avec succès.<br><small>$stepsMsg</small>";
            $_SESSION['sync_type'] = "success";
        } else {
            $errorsMsg = implode("<br>", $results['errors']);
            $_SESSION['sync_message'] = "Synchronisation partielle ou échouée.<br>$errorsMsg";
            $_SESSION['sync_type'] = "error";
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
    <link rel="stylesheet" href="styles.css?v=2">
    <link rel="stylesheet" href="loader.css?v=2">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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


    </style>
</head>
<body>

    <!-- Écran de chargement -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="loader-logo">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
            </div>
            <div class="loader-spinner">
                <div class="spinner-ring"></div>
                <div class="spinner-ring"></div>
                <div class="spinner-ring"></div>
            </div>
            <div class="loader-text">Chargement de l'administration...</div>
            <div class="loader-progress-bar">
                <div class="loader-progress-fill"></div>
            </div>
        </div>
    </div>



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
                    <form method="POST" class="sync-form-header">
                        <button type="submit" name="run_sync" class="btn-sync-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9"/>
                            </svg>
                            Synchroniser
                        </button>
                    </form>

                </div>

                <nav class="nav-buttons">
                    <button class="btn-theme-toggle" id="theme-toggle" aria-label="Basculer thème sombre">
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
            <div id="sync-loader" class="sync-loader" style="display: none;">
                <div class="loader-content-inline">
                    <div class="spinner-inline"></div>
                    <p class="loader-text-inline">Synchronisation avec ScoDoc en cours...</p>
                </div>
            </div>


            <!-- ALERT DE FEEDBACK -->
            <?php if (!empty($message_sync)): ?>
                <div class="admin-alert <?php echo ($message_type === 'success') ? 'alert-success' : 'alert-error'; ?>" style="position: relative;">
                    <?php if($message_type === 'success'): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php endif; ?>
                    <div style="flex: 1;"><?php echo $message_sync; ?></div>
                    <button type="button" class="close-alert" onclick="this.parentElement.style.display='none'">
                        <!-- Icone de 'Coche' pour Validation/Masquer (selon demande), ou Croix Classique. Utilisateur a dit 'une petite coche'. Je vais mettre une croix pour être sémantiquement 'close', mais visuellement propre. Je vais utiliser une croix car 'ne plus la voir' = fermer. 'Coche' = 'Done'. Je vais utiliser une icone qui fait sens pour 'dismiss'. -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3 class="form-title">Accès aux parcours des étudiants</h3>
                <form class="search-form" id="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <select id="formation" class="form-select" aria-label="Sélectionner la formation">
                                <option value="">Choisir une formation...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select id="annee" class="form-select" aria-label="Sélectionner l'année scolaire">
                                <option value="">Choisir une année...</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filtres Avancés -->
                    <div class="advanced-filters" style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1);">
                        <div style="font-weight: bold; margin-bottom: 10px; color: #a0aec0; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px;">FILTRES AVANCÉS</div>
                        
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <!-- Filtre Régime -->
                            <div class="filter-group-start">
                                <label style="font-size: 0.9rem; margin-right: 10px; font-weight:600;">Régime :</label>
                                <div class="radio-group" style="display: inline-flex; gap: 10px;">
                                    <label><input type="radio" name="regime" value="ALL" checked> Tous</label>
                                    <label><input type="radio" name="regime" value="FI"> FI (Formation Initiale)</label>
                                    <label><input type="radio" name="regime" value="FA"> FA (Alternance)</label>
                                </div>
                            </div>
                            
                            <!-- Filtre Statut -->
                            <div class="filter-group-end">
                                <label for="filter-status" style="font-size: 0.9rem; margin-right: 10px; font-weight:600;">Réussite :</label>
                                <select id="filter-status" class="form-select-sm" style="padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: #1a202c; color: #e2e8f0; font-size: 0.9rem; cursor: pointer;">
                                    <option value="ALL">Tout afficher</option>
                                    <option value="PASS_OK">Validation (Sans dette)</option>
                                    <option value="PASS_DEBT">Validation (Avec dette/jury)</option>
                                    <option value="FAIL">Échecs / Redoublements</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="quick-filters">
                        <span class="quick-label">Accès rapide :</span>
                        <button type="button" class="chip" onclick="quickSelect('BUT INFO')">Informatique</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT GEA')">GEA</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT R&T')">R&T</button>
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
                <div class="results-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="results-title">Bilan des compétences & Décisions de jury</h3>
                        <p class="results-subtitle" id="stats-subtitle">Formation : - • Année : -</p>
                    </div>
                    <button type="button" class="btn-submit" id="btn-pdf" onclick="exportPDF()" style="padding: 0.5rem 1rem; margin-left: auto;">
                        Exporter en PDF
                    </button>
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

                <div class="total-section" style="justify-content: flex-start; gap: 0.5rem; font-size: 1.1rem;">
                    <span class="total-label" style="font-weight: 600;">Total des étudiants :</span>
                    <span class="total-number" id="total-students" style="font-weight: bold; font-size: inherit;">0</span>
                </div>

                <div class="info-section">
                    <h4 class="info-title">Informations sur le diagramme</h4>
                    <p class="info-text"><strong>Entrées (Gauche) :</strong> Sources d'arrivée des étudiants (Parcoursup, Redoublants, Passerelles).</p>
                    <p class="info-text"><strong>Flux (Centre) :</strong> Parcours BUT1 ⇨ BUT2 ⇨ BUT3. L'épaisseur représente le nombre d'étudiants.</p>
                    <p class="info-text"><strong>Sorties (Droite) :</strong> Diplômés, Abandons, Réorientations.</p>
                    <p class="info-note"><strong>Astuce :</strong> Survolez les flux pour voir le nombre exact d'étudiants.</p>
                </div>
            </div>


        </div>

        <!-- SECTION ADMINISTRATION AVANCÉE -->
        <div class="container" style="margin-top: 3rem;">
            <h2 class="config-main-title">
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
                    <button type="submit" name="add_mapping" class="btn-shiny" style="align-self: end;">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Ajouter
                    </button>
                </form>
                
                <!-- Espacement -->
                <div style="margin-top: 1rem;"></div>
                
                <!-- Liste des mappings existants -->
                <div style="margin-top: 1.5rem;">
                    <h4 class="config-subtitle">Mappings existants</h4>
                    <?php
                    try {
                        $stmtMappings = $pdo->query("SELECT id, code_scodoc, libelle_graphique FROM mapping_codes ORDER BY code_scodoc");
                        $mappingsList = $stmtMappings->fetchAll(PDO::FETCH_ASSOC);
                        if (count($mappingsList) > 0): ?>
                            <table class="config-table">
                                <thead>
                                    <tr>
                                        <th>Code ScoDoc</th>
                                        <th>Libellé Affiché</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mappingsList as $m): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($m['code_scodoc']); ?></td>
                                        <td><?php echo htmlspecialchars($m['libelle_graphique']); ?></td>
                                        <td style="text-align: center;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_mapping_id" value="<?php echo $m['id']; ?>">
                                                <button type="submit" name="delete_mapping" class="btn-delete-item">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="config-empty">Aucun mapping défini.</p>
                        <?php endif;
                    } catch (Exception $e) { ?>
                        <p class="config-empty">Aucun mapping défini.</p>
                    <?php } ?>
                </div>
            </div>
            
            <!-- Interface Scénarios -->
            <div class="config-card">
                <h3 style="color: var(--heading-color, #1a3a5c); margin-bottom: 1rem;">Règles de Scénarios (Flux)</h3>
                <p style="color: var(--text-muted, #666); margin-bottom: 1.5rem;">Définissez comment les transitions sont classifiées dans le Sankey. Les noeuds sont les étapes du parcours étudiant.</p>
                
                <form method="POST" style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr 1fr auto;">
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Noeud Source</label>
                        <select name="scenario_source" class="config-input">
                            <option value="">Sélectionner...</option>
                            <option value="Nouveaux inscrits">Nouveaux inscrits</option>
                            <option value="Redoublant">Redoublant</option>
                            <option value="Passerelle">Passerelle</option>
                            <option value="BUT1">BUT1</option>
                            <option value="BUT2">BUT2</option>
                            <option value="BUT3">BUT3</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Noeud Cible</label>
                        <select name="scenario_target" class="config-input">
                            <option value="">Sélectionner...</option>
                            <option value="BUT1">BUT1</option>
                            <option value="BUT2">BUT2</option>
                            <option value="BUT3">BUT3</option>
                            <option value="Diplômé">Diplômé</option>
                            <option value="Abandon BUT1">Abandon BUT1</option>
                            <option value="Abandon BUT2">Abandon BUT2</option>
                            <option value="Abandon BUT3">Abandon BUT3</option>
                            <option value="Redoublement BUT1">Redoublement BUT1</option>
                            <option value="Redoublement BUT2">Redoublement BUT2</option>
                            <option value="Redoublement BUT3">Redoublement BUT3</option>
                            <option value="En cours BUT1">En cours BUT1</option>
                            <option value="En cours BUT2">En cours BUT2</option>
                            <option value="En cours BUT3">En cours BUT3</option>
                            <option value="Réorientation">Réorientation</option>
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
                    <button type="submit" name="add_scenario" class="btn-shiny" style="align-self: end;">
                         <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Ajouter
                    </button>
                </form>
                
                <div class="config-example">
                    <strong>Exemple :</strong> Pour renommer le flux "BUT1 → BUT2" en ajoutant "(Pass.)", créez : Source = "BUT1", Cible = "BUT2", Type = "passerelle".
                </div>
                
                <!-- Liste des scénarios existants -->
                <div style="margin-top: 1.5rem;">
                    <h4 class="config-subtitle">Scénarios existants</h4>
                    <?php
                    try {
                        $stmtScenarios = $pdo->query("SELECT id_scenario, formation_source, formation_cible, type_flux FROM scenario_correspondance ORDER BY formation_source");
                        $scenariosList = $stmtScenarios->fetchAll(PDO::FETCH_ASSOC);
                        if (count($scenariosList) > 0): ?>
                            <table class="config-table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Cible</th>
                                        <th>Type</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scenariosList as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['formation_source']); ?></td>
                                        <td><?php echo htmlspecialchars($s['formation_cible']); ?></td>
                                        <td>
                                            <span class="chip-scenario <?php echo htmlspecialchars($s['type_flux']); ?>">
                                                <?php echo htmlspecialchars($s['type_flux']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_scenario_id" value="<?php echo $s['id_scenario']; ?>">
                                                <button type="submit" name="delete_scenario" class="btn-delete-item">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="config-empty">Aucun scénario défini.</p>
                        <?php endif;
                    } catch (Exception $e) { ?>
                        <p class="config-empty">Aucun scénario défini.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </main>

    <!-- MODALE LISTE ETUDIANTS (Conformité Cahier des Charges) -->
    <div id="student-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title">Étudiants du flux</h3>
            <p id="modal-subtitle" style="color: #666; margin-bottom: 1rem;">Liste des étudiants concernés par ce parcours.</p>
            <div id="student-list-container" class="student-list">
                <p>Chargement...</p>
            </div>

        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p class="footer-copyright">© 2025 Mnémosyne - Université Sorbonne Paris Nord</p>
        </div>
    </footer>
    <script src="loader.js?v=2"></script>
    <script src="script.js?v=2"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script>
    function exportPDF() {
        const formation = document.getElementById('formation').value;
        const annee = document.getElementById('annee').value;

        if (!formation || !annee) {
            alert("Veuillez d'abord visualiser un diagramme.");
            return;
        }

        // Feedback utilisateur
        const btn = document.getElementById('btn-pdf');
        const originalText = btn.textContent;
        btn.textContent = "⏳ Génération...";
        btn.disabled = true;

        // Récupérer les stats
        const getVal = (id) => document.getElementById(id) ? document.getElementById(id).textContent : '0';

        const stats = {
            valide: getVal('count-valide'),
            validePct: getVal('percent-valide'),
            partiel: getVal('count-partiel'),
            partielPct: getVal('percent-partiel'),
            red: getVal('count-red'),
            redPct: getVal('percent-red'),
            abd: getVal('count-abd'),
            abdPct: getVal('percent-abd'),
            total: getVal('total-students')
        };

        // Créer le PDF avec jsPDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4'); // Portrait, millimètres, A4

        // Titre principal
        doc.setFontSize(22);
        doc.setTextColor(30, 58, 95); // #1e3a5f
        doc.text('Bilan de Cohorte', 105, 25, { align: 'center' });

        // Ligne sous le titre
        doc.setDrawColor(45, 90, 140); // #2d5a8c
        doc.setLineWidth(0.5);
        doc.line(20, 30, 190, 30);

        // Sous-titre (Formation et Année)
        doc.setFontSize(14);
        doc.setTextColor(85, 85, 85);
        doc.text(`Formation : ${formation}`, 105, 40, { align: 'center' });
        doc.text(`Année : ${annee}`, 105, 48, { align: 'center' });

        // Section 1 : Synthèse des Résultats
        doc.setFontSize(16);
        doc.setTextColor(45, 90, 140);
        doc.text('1. Synthèse des Résultats', 20, 65);

        // Tableau avec autoTable
        doc.autoTable({
            startY: 70,
            head: [['Catégorie', 'Nombre', 'Pourcentage']],
            body: [
                ['Diplômé / Admis', stats.valide, stats.validePct],
                ['En cours', stats.partiel, stats.partielPct],
                ['Redoublement', stats.red, stats.redPct],
                ['Abandon / Réorientation', stats.abd, stats.abdPct],
                ['TOTAL', stats.total, '100%']
            ],
            headStyles: { 
                fillColor: [240, 244, 248], 
                textColor: [30, 58, 95],
                fontStyle: 'bold'
            },
            bodyStyles: { 
                textColor: [51, 51, 51]
            },
            alternateRowStyles: { 
                fillColor: [249, 249, 249] 
            },
            footStyles: {
                fillColor: [233, 236, 239],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            styles: {
                halign: 'center',
                cellPadding: 4
            },
            columnStyles: {
                0: { halign: 'left' }
            },
            margin: { left: 20, right: 20 }
        });

        // Section 2 : Note sur le diagramme
        const finalY = doc.lastAutoTable.finalY + 15;
        doc.setFontSize(16);
        doc.setTextColor(45, 90, 140);
        doc.text('2. Visualisation des Flux', 20, finalY);

        doc.setFontSize(11);
        doc.setTextColor(100, 100, 100);
        doc.text('Le diagramme Sankey est disponible dans l\'interface web.', 20, finalY + 10);
        doc.text('Consultez l\'application pour une visualisation interactive des flux étudiants.', 20, finalY + 18);

        // Pied de page
        doc.setFontSize(10);
        doc.setTextColor(150, 150, 150);
        doc.text(`Généré le ${new Date().toLocaleDateString()} via Mnémosyne`, 105, 285, { align: 'center' });

        // Sauvegarder
        doc.save(`Rapport_${formation.replace(/[^a-zA-Z0-9]/g, '_')}_${annee}.pdf`);

        // Restaurer le bouton
        btn.textContent = originalText;
        btn.disabled = false;
    }
    </script>
</body>
</html>
