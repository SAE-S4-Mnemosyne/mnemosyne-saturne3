<?php
require_once 'config.php';
// $pdo est déjà initialisé dans config.php
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MNEMOSYNE</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="stylesheet" href="styles.css?v=2">
    <link rel="stylesheet" href="loader.css?v=2">

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
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
            <div class="loader-text">Chargement de Mnémosyne...</div>
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
                        <p class="logo-subtitle">Université Sorbonne Paris Nord</p>
                    </div>
                </div>

                <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false" onclick="toggleMenu()">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </button>

                <nav class="nav-buttons" id="nav-menu">
                    <button class="btn-theme-toggle" id="theme-toggle" aria-label="Basculer le thème sombre">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="currentColor"/>
                        </svg>
                    </button>
                    <a href="login.html" class="btn-nav btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Connexion
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
                        <path
                            d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <h2 class="hero-title">Suivi des Cohortes</h2>
                <p class="hero-subtitle">IUT de Villetaneuse</p>
                <p class="hero-quote">"Garder la mémoire, éclairer les parcours"</p>
            </div>

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
                    <p id="statut-promo" style="display:none; font-weight: bold; margin-top: 0.5rem;"></p>
                </div>

                <div class="status-cards">
                    <div class="status-card status-validé">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line">
                                        <span class="status-code">ADM</span>
                                        <span class="status-label">Diplômé</span>
                                    </div>
                                    <p class="status-description">Obtention du diplôme BUT</p>
                                </div>
                            </div>
                            <div class="status-stats">
                                <div class="status-number" id="count-valide">0</div>
                                <div class="status-percent" id="percent-valide">0%</div>
                            </div>
                        </div>
                    </div>

                    <div class="status-card status-partiel">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line">
                                        <span class="status-code">PASS</span>
                                        <span class="status-label">En cours</span>
                                    </div>
                                    <p class="status-description">Étudiants en cours de cursus</p>
                                </div>
                            </div>
                            <div class="status-stats">
                                <div class="status-number" id="count-partiel">0</div>
                                <div class="status-percent" id="percent-partiel">0%</div>
                            </div>
                        </div>
                    </div>

                    <div class="status-card status-redoublement">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line">
                                        <span class="status-code">RED</span>
                                        <span class="status-label">Redoublants</span>
                                    </div>
                                    <p class="status-description">Redoublants entrants ou décision de redoublement</p>
                                </div>
                            </div>
                            <div class="status-stats">
                                <div class="status-number" id="count-red">0</div>
                                <div class="status-percent" id="percent-red">0%</div>
                            </div>
                        </div>
                    </div>

                    <div class="status-card status-abandon">
                        <div class="status-content">
                            <div class="status-info">
                                <div class="status-indicator"></div>
                                <div class="status-details">
                                    <div class="status-header-line">
                                        <span class="status-code">NAR</span>
                                        <span class="status-label">Sortie</span>
                                    </div>
                                    <p class="status-description">Abandon ou réorientation</p>
                                </div>
                            </div>
                            <div class="status-stats">
                                <div class="status-number" id="count-abd">0</div>
                                <div class="status-percent" id="percent-abd">0%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="total-section">
                    <span class="total-label">Total des étudiants</span>
                    <span class="total-number" id="total-students">0</span>
                </div>

                <div class="info-section">
                    <h4 class="info-title">Informations sur le diagramme</h4>
                    <p class="info-text"><strong>Entrées (Gauche) :</strong> Les différentes sources d'arrivée des étudiants (Parcoursup, Redoublants, Passerelles).</p>
                    <p class="info-text"><strong>Flux (Centre) :</strong> Les parcours des étudiants BUT1 ⇨ BUT2 ⇨ BUT3. L'épaisseur représente le nombre d'étudiants.</p>
                    <p class="info-text"><strong>Sorties (Droite) :</strong> Diplômés, Abandons, Réorientations.</p>
                    <p class="info-note"><strong>Astuce :</strong> Survolez les flux pour voir le nombre exact d'étudiants concernés.</p>
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
</body>
</html>