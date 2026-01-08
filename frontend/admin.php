<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Mnémosyne</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <img src="assets/logo.png" alt="Logo Mnémosyne" class="logo-image"
                        style="height: 50px; width: auto;">
                    <div class="logo-text">
                        <h1 class="logo-title">MNEMOSYNE</h1>
                        <p class="logo-subtitle">Espace Administration</p>
                    </div>
                </div>

                <!-- Bouton de synchro au MILIEU (Centrage absolu) -->
                <div class="header-center-absolute">
                    <button id="btn-sync" class="btn-sync" onclick="triggerSync()">
                        Synchroniser
                    </button>
                </div>

                <!-- Navigation à DROITE -->
                <nav class="nav-buttons" style="gap: 0.5rem;">
                    <button class="btn-theme-toggle" id="theme-toggle" aria-label="Mode sombre">🌙</button>
                    <a href="index.html" class="btn-logout">
                        <span class="icon" style="font-size: 1.2rem; line-height: 0;">⏻</span>
                        Déconnexion
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-section">
        <div class="container">

            <!-- Zone de notification Sync -->
            <div id="sync-notification"
                style="display:none; padding: 1rem; margin-bottom: 2rem; border-radius: 8px; text-align: center;"></div>

            <!-- État de la base (Mini Dashboard) -->
            <div class="features" style="margin-bottom: 3rem;">
                <div class="feature-card" style="text-align: center; padding: 1.5rem;">
                    <h4 class="feature-title">État de la Base</h4>
                    <p class="feature-text" id="db-status-text">Chargement...</p>
                </div>
            </div>

            <div class="form-container">
                <h3 class="form-title">Visualisation des parcours | Vue Admin</h3>
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
                    <button type="submit" class="btn-submit">
                        Voir les parcours
                        <span class="arrow">→</span>
                    </button>
                </form>
            </div>

            <!-- Conteneur du graphique Sankey -->
            <div id="sankey_chart"
                style="width: 100%; min-height: 400px; margin-top: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            </div>

        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p class="footer-copyright">2025 Mnémosyne - Administration</p>
        </div>
    </footer>
    <script src="script.js"></script>
    <script>
        // Script spécifique pour la synchro Admin
        async function triggerSync() {
            const notif = document.getElementById('sync-notification');
            const btn = document.getElementById('btn-sync');

            notif.style.display = 'block';
            notif.style.background = '#e3f2fd';
            notif.style.color = '#0d47a1';
            notif.innerHTML = 'Synchronisation en cours...';
            btn.disabled = true;

            try {
                // On appelle le fichier PHP. Note: sync_data.php renvoie du texte/HTML pour l'instant.
                const response = await fetch('../Backend/code_Mnémosyne/sync_data.php');
                const text = await response.text();

                if (response.ok) {
                    notif.style.background = '#e8f5e9';
                    notif.style.color = '#1b5e20';
                    notif.innerHTML = 'Synchronisation terminée avec succès !';

                    // Recharger les stats et les options
                    loadDbStatus();
                    loadOptions(); // Rafraichir les listes
                } else {
                    throw new Error("Erreur serveur");
                }
            } catch (error) {
                notif.style.background = '#ffebee';
                notif.style.color = '#c62828';
                notif.innerHTML = 'Erreur lors de la synchronisation.';
                console.error(error);
            } finally {
                btn.disabled = false;
                setTimeout(() => { notif.style.display = 'none'; }, 5000);
            }
        }

        // Script spécifique pour l'état de la base (Simulation simple ou appel API futur)
        async function loadDbStatus() {
            // Pour l'instant on simule ou on checke si les options se chargent
            const statusDiv = document.getElementById('db-status-text');
            try {
                const response = await fetch('../Backend/code_Mnémosyne/api/get_options.php');
                const data = await response.json();
                if (data.formations && data.formations.length > 0) {
                    statusDiv.innerHTML = `🟢 Base active : ${data.formations.length} formations disponibles.`;
                    statusDiv.style.color = "#4caf50";
                } else {
                    statusDiv.innerHTML = `🟠 Base vide ou non connectée.`;
                    statusDiv.style.color = "#ff9800";
                }
            } catch (e) {
                statusDiv.innerHTML = `🔴 Erreur de connexion BDD.`;
                statusDiv.style.color = "#f44336";
            }
        }

        // Charger le status au démarrage
        document.addEventListener("DOMContentLoaded", loadDbStatus);
    </script>
</body>

</html>