<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - MNEMOSYNE</title>
    <meta name="description" content="Interface d'administration Mnemosyne - Gestion des donnees et synchronisation ScoDoc">
    <link rel="icon" type="image/png" href="/assets/logo.png?v=3">
    <link rel="stylesheet" href="styles.css?v=2">
    <link rel="stylesheet" href="loader.css?v=2">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
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

    <!-- Ecran de chargement -->
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
                    <img src="assets/logo.png" alt="Logo Mnemosyne" class="logo-image">
                    <div class="logo-text">
                        <h1 class="logo-title">MNEMOSYNE</h1>
                        <p class="logo-subtitle">Administration</p>
                    </div>
                </div>

                <div class="header-center" style="display: flex; gap: 0.5rem;">
                    <form method="POST" class="sync-form-header" id="sync-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" name="run_sync" class="btn-sync-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9"/>
                            </svg>
                            Synchroniser
                        </button>
                    </form>
                </div>

                <nav class="nav-buttons">
                    <button class="btn-theme-toggle" id="theme-toggle" aria-label="Basculer theme sombre">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="currentColor"/>
                        </svg>
                    </button>
                    <a href="admin.php?logout=true" class="btn-nav btn-logout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Deconnexion
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
                <p class="hero-quote">"Garder la memoire, eclairer les parcours"</p>
            </div>

            <!-- Loader sync inline -->
            <div id="sync-loader" class="sync-loader" style="display: none;">
                <div class="loader-content-inline">
                    <div class="spinner-inline"></div>
                    <p class="loader-text-inline">Synchronisation avec ScoDoc en cours...</p>
                </div>
            </div>

            <!-- Alert feedback -->
            <?php if (!empty($message)): ?>
                <div class="admin-alert <?php echo ($messageType === 'success') ? 'alert-success' : 'alert-error'; ?>" style="position: relative;">
                    <?php if($messageType === 'success'): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php endif; ?>
                    <div style="flex: 1;"><?php echo $message; ?></div>
                    <button type="button" class="close-alert" onclick="this.parentElement.style.display='none'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3 class="form-title">Acces aux parcours des etudiants</h3>
                <form class="search-form" id="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <select id="formation" class="form-select" aria-label="Selectionner la formation">
                                <option value="">Choisir une formation...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select id="annee" class="form-select" aria-label="Selectionner l'annee scolaire">
                                <option value="">Choisir une annee...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filtres avances -->
                    <div class="advanced-filters" style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1);">
                        <div style="font-weight: bold; margin-bottom: 10px; color: #a0aec0; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px;">FILTRES AVANCES</div>
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <div class="filter-group-start">
                                <label style="font-size: 0.9rem; margin-right: 10px; font-weight:600;">Regime :</label>
                                <div class="radio-group" style="display: inline-flex; gap: 10px;">
                                    <label><input type="radio" name="regime" value="ALL" checked> Tous</label>
                                    <label><input type="radio" name="regime" value="FI"> FI (Formation Initiale)</label>
                                    <label><input type="radio" name="regime" value="FA"> FA (Alternance)</label>
                                </div>
                            </div>
                            <div class="filter-group-end">
                                <label for="filter-status" style="font-size: 0.9rem; margin-right: 10px; font-weight:600;">Reussite :</label>
                                <select id="filter-status" class="form-select-sm" style="padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: #1a202c; color: #e2e8f0; font-size: 0.9rem; cursor: pointer;">
                                    <option value="ALL">Tout afficher</option>
                                    <option value="PASS_OK">Validation (Sans dette)</option>
                                    <option value="PASS_DEBT">Validation (Avec dette/jury)</option>
                                    <option value="FAIL">Echecs / Redoublements</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="quick-filters">
                        <span class="quick-label">Acces rapide :</span>
                        <button type="button" class="chip" onclick="quickSelect('BUT INFO')">Informatique</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT GEA')">GEA</button>
                        <button type="button" class="chip" onclick="quickSelect('BUT R&T')">R&T</button>
                    </div>

                    <button type="submit" class="btn-submit">
                        Voir les parcours
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 8px; vertical-align: middle;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>
            </div>

            <div id="chart-wrapper" style="position: relative;">
                <div id="chart-loader" class="chart-loader" style="display: none;">
                    <div class="spinner"></div>
                    <p>Chargement des donnees...</p>
                </div>
                <div id="sankey_chart" class="chart-box">
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1dce5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                            <path d="M2 12h20M2 12l5-5m-5 5l5 5"/>
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <h4>En attente de selection</h4>
                        <p>Selectionnez une formation ci-dessus pour visualiser les flux.</p>
                    </div>
                </div>
            </div>

            <div class="results-section" id="results-section" style="display:none; margin-top: 3rem;">
                <div class="results-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="results-title">Bilan des competences & Decisions de jury</h3>
                        <p class="results-subtitle" id="stats-subtitle">Formation : - -- Annee : -</p>
                    </div>
                    <button type="button" class="btn-submit" id="btn-pdf" onclick="exportPDF()" style="padding: 0.5rem 1rem; margin-left: auto;">
                        Exporter en PDF
                    </button>
                </div>

                <!-- Status Cards -->
                <div class="status-cards">
                    <div class="status-card status-valid&eacute;">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line"><span class="status-code">ADM</span><span class="status-label">Diplome</span></div>
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
                                    <div class="status-header-line"><span class="status-code">NAR/DEF</span><span class="status-label">Abandon/Reo.</span></div>
                                </div>
                            </div>
                            <div class="status-stats"><div class="status-number" id="count-abd">0</div><div class="status-percent" id="percent-abd">0%</div></div>
                        </div>
                    </div>
                </div>

                <div class="total-section" style="justify-content: flex-start; gap: 0.5rem; font-size: 1.1rem;">
                    <span class="total-label" style="font-weight: 600;">Total des etudiants :</span>
                    <span class="total-number" id="total-students" style="font-weight: bold; font-size: inherit;">0</span>
                </div>

                <div class="info-section">
                    <h4 class="info-title">Informations sur le diagramme</h4>
                    <p class="info-text"><strong>Entrees (Gauche) :</strong> Sources d'arrivee des etudiants (Parcoursup, Redoublants, Passerelles).</p>
                    <p class="info-text"><strong>Flux (Centre) :</strong> Parcours BUT1 => BUT2 => BUT3. L'epaisseur represente le nombre d'etudiants.</p>
                    <p class="info-text"><strong>Sorties (Droite) :</strong> Diplomes, Abandons, Reorientations.</p>
                    <p class="info-note"><strong>Astuce :</strong> Survolez les flux pour voir le nombre exact d'etudiants.</p>
                </div>
                </div>
            </div>

        </div>

        <!-- Section administration avancee -->
        <div class="container" style="margin-top: 3rem;">
            <h2 class="config-main-title">Configuration Avancee</h2>

            <!-- Interface Mapping -->
            <div class="config-card">
                <h3 style="color: var(--heading-color, #1a3a5c); margin-bottom: 1rem;">Mapping des Codes ScoDoc</h3>
                <p style="color: var(--text-muted, #666); margin-bottom: 1.5rem;">Associez les codes techniques ScoDoc a des libelles lisibles pour les graphiques.</p>

                <form method="POST" style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr auto;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Code ScoDoc</label>
                        <input type="text" name="mapping_code" placeholder="Ex: B1-INFO-FI" class="config-input">
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Libelle Affiche</label>
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

                <div style="margin-top: 1.5rem;">
                    <h4 class="config-subtitle">Mappings existants</h4>
                    <?php if (count($mappings) > 0): ?>
                        <table class="config-table">
                            <thead><tr><th>Code ScoDoc</th><th>Libelle Affiche</th><th style="text-align: center;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($mappings as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['code_scodoc']); ?></td>
                                    <td><?php echo htmlspecialchars($m['libelle_graphique']); ?></td>
                                    <td style="text-align: center;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="delete_mapping_id" value="<?php echo $m['id']; ?>">
                                            <button type="submit" name="delete_mapping" class="btn-delete-item">X</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="config-empty">Aucun mapping defini.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interface Scenarios -->
            <div class="config-card">
                <h3 style="color: var(--heading-color, #1a3a5c); margin-bottom: 1rem;">Regles de Scenarios (Flux)</h3>
                <p style="color: var(--text-muted, #666); margin-bottom: 1.5rem;">Definissez comment les transitions sont classifiees dans le Sankey.</p>

                <form method="POST" style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr 1fr auto;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Formation Source</label>
                        <select name="scenario_source" class="config-input">
                            <option value="">Selectionner...</option>
                            <?php foreach ($formations as $f): ?>
                                <option value="<?php echo $f['id_formation']; ?>"><?php echo htmlspecialchars($f['titre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Formation Cible</label>
                        <select name="scenario_target" class="config-input">
                            <option value="">Selectionner...</option>
                            <?php foreach ($formations as $f): ?>
                                <option value="<?php echo $f['id_formation']; ?>"><?php echo htmlspecialchars($f['titre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--text-color, #333);">Type de Flux</label>
                        <select name="scenario_type" class="config-input">
                            <option value="passage">Passage Normal</option>
                            <option value="redoublement">Redoublement</option>
                            <option value="passerelle">Passerelle</option>
                            <option value="reorientation">Reorientation</option>
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

                <div style="margin-top: 1.5rem;">
                    <h4 class="config-subtitle">Scenarios existants</h4>
                    <?php if (count($scenarios) > 0): ?>
                        <table class="config-table">
                            <thead><tr><th>Source</th><th>Cible</th><th>Type</th><th style="text-align: center;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($scenarios as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['formation_source']); ?></td>
                                    <td><?php echo htmlspecialchars($s['formation_cible']); ?></td>
                                    <td><span class="chip-scenario <?php echo htmlspecialchars($s['type_flux']); ?>"><?php echo htmlspecialchars($s['type_flux']); ?></span></td>
                                    <td style="text-align: center;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="delete_scenario_id" value="<?php echo $s['id_scenario']; ?>">
                                            <button type="submit" name="delete_scenario" class="btn-delete-item">X</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="config-empty">Aucun scenario defini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modale liste etudiants -->
    <div id="student-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title">Etudiants du flux</h3>
            <p id="modal-subtitle" style="color: #666; margin-bottom: 1rem;">Liste des etudiants concernes par ce parcours.</p>
            <div id="student-list-container" class="student-list">
                <p>Chargement...</p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p class="footer-copyright">&copy; 2025 Mnemosyne - Universite Sorbonne Paris Nord</p>
        </div>
    </footer>
    <script src="loader.js?v=2"></script>
    <script src="script.js?v=2"></script>

    <script>
        //script pour le fichier pdf
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    function exportPDF() {
        const formation = document.getElementById('formation').value;
        const annee = document.getElementById('annee').value;
        if (!formation || !annee) { alert("Veuillez d'abord visualiser un diagramme."); return; }
        const btn = document.getElementById('btn-pdf');
        const originalText = btn.textContent;
        btn.textContent = "Generation...";
        btn.disabled = true;
        const getVal = (id) => document.getElementById(id) ? document.getElementById(id).textContent : '0';
        const stats = {
            valide: getVal('count-valide'), validePct: getVal('percent-valide'),
            partiel: getVal('count-partiel'), partielPct: getVal('percent-partiel'),
            red: getVal('count-red'), redPct: getVal('percent-red'),
            abd: getVal('count-abd'), abdPct: getVal('percent-abd'),
            total: getVal('total-students')
        };
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        doc.setFontSize(22); doc.setTextColor(30, 58, 95);
        doc.text('Bilan de Cohorte', 105, 25, { align: 'center' });
        doc.setDrawColor(45, 90, 140); doc.setLineWidth(0.5); doc.line(20, 30, 190, 30);
        doc.setFontSize(14); doc.setTextColor(85, 85, 85);
        doc.text(`Formation : ${formation}`, 105, 40, { align: 'center' });
        doc.text(`Annee : ${annee}`, 105, 48, { align: 'center' });
        doc.setFontSize(16); doc.setTextColor(45, 90, 140);
        doc.text('1. Synthese des Resultats', 20, 65);
        doc.autoTable({
            startY: 70,
            head: [['Categorie', 'Nombre', 'Pourcentage']],
            body: [
                ['Diplome / Admis', stats.valide, stats.validePct],
                ['En cours', stats.partiel, stats.partielPct],
                ['Redoublement', stats.red, stats.redPct],
                ['Abandon / Reorientation', stats.abd, stats.abdPct],
                ['TOTAL', stats.total, '100%']
            ],
            headStyles: { fillColor: [240, 244, 248], textColor: [30, 58, 95], fontStyle: 'bold' },
            bodyStyles: { textColor: [51, 51, 51] },
            alternateRowStyles: { fillColor: [249, 249, 249] },
            styles: { halign: 'center', cellPadding: 4 },
            columnStyles: { 0: { halign: 'left' } },
            margin: { left: 20, right: 20 }
        });
        const finalY = doc.lastAutoTable.finalY + 15;
        doc.setFontSize(16); doc.setTextColor(45, 90, 140);
        doc.text('2. Visualisation des Flux', 20, finalY);
        doc.setFontSize(11); doc.setTextColor(100, 100, 100);
        doc.text('Le diagramme Sankey est disponible dans l\'interface web.', 20, finalY + 10);
        doc.setFontSize(10); doc.setTextColor(150, 150, 150);
        doc.text(`Genere le ${new Date().toLocaleDateString()} via Mnemosyne`, 105, 285, { align: 'center' });
        doc.save(`Rapport_${formation.replace(/[^a-zA-Z0-9]/g, '_')}_${annee}.pdf`);
        btn.textContent = originalText;
        btn.disabled = false;
    }
    // ... fin de ma fonction exportPDF() ...
  

// J'insère ma fonction exportJSON() juste ici # code mis à jour par amel le 19 /06/2026
function exportJSON() {
    const formation = document.getElementById('formation').value;
    const annee = document.getElementById('annee').value;
    if (!formation || !annee) { alert("Veuillez d'abord visualiser un diagramme."); return; }

    const getVal = (id) => document.getElementById(id) ? document.getElementById(id).textContent : '0';
    
    const dataExport = {
        projet: "MNEMOSYNE",
        extraction_date: new Date().toISOString(),
        criteres: { formation: formation, annee: annee },
        statistiques: {
            diplome_admis: { quantite: parseInt(getVal('count-valide')), pourcentage: getVal('percent-valide') },
            en_cours: { quantite: parseInt(getVal('count-partiel')), pourcentage: getVal('percent-partiel') },
            redoublement: { quantite: parseInt(getVal('count-red')), pourcentage: getVal('percent-red') },
            abandon_reorientation: { quantite: parseInt(getVal('count-abd')), pourcentage: getVal('percent-abd') },
            total_etudiants: parseInt(getVal('total-students'))
        }
    };

    const jsonString = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(dataExport, null, 4));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", jsonString);
    downloadAnchor.setAttribute("download", `Export_${formation.replace(/[^a-zA-Z0-9]/g, '_')}_${annee}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
}
    </script>
    </body>
</html>
