// Charger la librairie Google Charts
google.charts.load('current', { 'packages': ['sankey'] });

document.addEventListener("DOMContentLoaded", () => {
    // 1. Initialiser le thème (Dark Mode)
    initTheme();

    // 2. Charger les options des formulaires
    loadOptions();

    // 3. Ecouteur sur le formulaire
    const form = document.getElementById("filter-form");
    if (form) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            fetchAndDraw();
        });
    }

    // 4. Ecouteur Mode Sombre bouton
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', toggleTheme);
    }

    // 5. Injection des styles dynamiques (Centrage Modale + Couleurs)
    injectGlobalStyles();
});

function injectGlobalStyles() {
    const styleId = 'global-styles-fix';
    if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            /* Centrage Modale via Flexbox */
            #student-modal {
                display: none; /* JS bascule en Flex */
                align-items: center;
                justify-content: center;
                padding-top: 0 !important; /* Override styles existants */
            }

            .modal-content {
                max-height: 90vh;
                overflow-y: auto;
                margin: 0 !important; /* Centré par le parent Flex */
                position: relative;
                top: auto;
                left: auto;
                transform: none;
            }

            /* Styles classes pour compatibilité (si inline échoue) */
            .decision-success { background-color: #28a745 !important; color: #fff !important; }
            .decision-warning { background-color: #ffc107 !important; color: #1f2d3d !important; }
            .decision-danger { background-color: #dc3545 !important; color: #fff !important; }
            .decision-secondary { background-color: #6c757d !important; color: #fff !important; }
            .decision-info { background-color: #17a2b8 !important; color: #fff !important; }
        `;
        document.head.appendChild(style);
    }
}

// Gestion du Menu Hamburger
function toggleMenu() {
    const nav = document.getElementById('nav-menu');
    nav.classList.toggle('active');
}

// Gestion du Thème (Dark/Light)
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const body = document.body;
    // const btn = document.getElementById('theme-toggle'); // Les icônes sont gérées par CSS/SVG

    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
    } else {
        body.classList.remove('dark-mode');
    }
}

function toggleTheme() {
    const body = document.body;
    // const btn = document.getElementById('theme-toggle');
    body.classList.toggle('dark-mode');

    if (body.classList.contains('dark-mode')) {
        localStorage.setItem('theme', 'dark');
    } else {
        localStorage.setItem('theme', 'light');
    }
}

// Chargement des Options
async function loadOptions() {
    try {
        const response = await fetch('api/recuperer_options.php');
        const data = await response.json();
        populateSelects(data);

    } catch (error) {
        console.warn("API non accessible ou BDD vide, chargement des données de secours...", error);
        // Données de Fallback (Secours) pour que l'interface fonctionne
        const fallbackData = {
            formations: ["BUT Informatique", "BUT GEA", "BUT R&T", "BUT GEII"],
            annees: ["2023-2024", "2022-2023", "2021-2022"]
        };
        populateSelects(fallbackData);
    }
}

function populateSelects(data) {
    const formationSelect = document.getElementById("formation");
    const anneeSelect = document.getElementById("annee");

    // Remplir Formations
    if (data.formations && data.formations.length > 0) {
        formationSelect.innerHTML = '<option value="">Choisir une formation...</option>';

        // Ajouter l'option "Tout l'IUT" en premier
        const optAll = document.createElement("option");
        optAll.value = "__ALL__";
        optAll.textContent = "Tout l'IUT (Vue globale)";
        formationSelect.appendChild(optAll);

        // Ajouter les formations individuelles
        data.formations.forEach(f => {
            const opt = document.createElement("option");
            opt.value = f;
            opt.textContent = f;
            formationSelect.appendChild(opt);
        });
    }

    // Remplir Années
    if (data.annees && data.annees.length > 0) {
        anneeSelect.innerHTML = '<option value="">Choisir une année...</option>';
        const anneesUniques = new Set();
        data.annees.forEach(a => {
            if (a) {
                const anneeDebut = a.split('-')[0];
                anneesUniques.add(anneeDebut);
            }
        });

        Array.from(anneesUniques).sort().reverse().forEach(a => {
            const opt = document.createElement("option");
            opt.value = a;
            // Si la BDD contient 2022 (année de jury), c'est l'année scolaire 2021-2022
            opt.textContent = (parseInt(a) - 1) + "/" + a;
            anneeSelect.appendChild(opt);
        });
    }
}

// Fonction Chips (Accès rapide & Auto-sélection dernière année)
function quickSelect(searchTerm) {
    const select = document.getElementById('formation');
    const anneeSelect = document.getElementById('annee');

    // Attendre que les options soient chargées (si moins de 2 options, elles ne sont pas encore là)
    if (select.options.length < 2) {
        console.log("Options pas encore chargées, retry dans 500ms...");
        setTimeout(() => quickSelect(searchTerm), 500);
        return;
    }

    // 1. Trouver la formation par correspondance partielle (insensible à la casse)
    let found = false;
    for (let i = 0; i < select.options.length; i++) {
        const optText = select.options[i].text.toLowerCase();
        // Recherche plus flexible : contient OU commence par
        if (optText.includes(searchTerm.toLowerCase())) {
            select.selectedIndex = i;
            found = true;
            // Animation de confirmation
            select.style.borderColor = "#28a745";
            setTimeout(() => select.style.borderColor = "", 400);
            break;
        }
    }

    if (!found) {
        console.warn(`Formation contenant "${searchTerm}" non trouvée dans:`,
            Array.from(select.options).map(o => o.text));
        alert(`Formation "${searchTerm}" non trouvée. Vérifiez la liste.`);
        return;
    }

    // 2. Sélectionner la première année (la plus récente)
    if (anneeSelect.options.length > 1) {
        anneeSelect.selectedIndex = 1;
        anneeSelect.style.borderColor = "#2d5a8c";
        setTimeout(() => anneeSelect.style.borderColor = "", 300);
    }

    // 3. Déclencher la recherche
    fetchAndDraw();
}

// Fonction Principale (Modifiée pour afficher le Jury sans les valeurs)
async function fetchAndDraw() {
    const formation = document.getElementById("formation").value;
    const annee = document.getElementById("annee").value;

    // Récupérer les filtres
    const regime = document.querySelector('input[name="regime"]:checked').value;
    const status = document.getElementById('filter-status').value;

    // Récupération des éléments d'interface (Chart)
    const chartContainer = document.getElementById("sankey_chart");

    if (!formation || !annee) {
        alert("Veuillez sélectionner une formation et une année.");
        return;
    }

    // 1. Afficher la section Jury (Juste le visuel, sans valeurs dynamiques)
    updateStatsDisplay(formation, annee);

    updateStatsDisplay(formation, annee);

    try {
        const timestamp = new Date().getTime();
        const url = `api/recuperer_donnees_flux.php?formation=${encodeURIComponent(formation)}&annee=${encodeURIComponent(annee)}&regime=${regime}&status=${status}&_t=${timestamp}`;
        console.log("Fetching Flow Data:", url);

        const response = await fetch(url);
        const data = await response.json();

        if (data.stats) {
            updateStatsUI(data.stats);
        }

        if (data.error) {
            chartContainer.innerHTML = `<p class="error">${data.error}</p>`;
            return;
        }

        if (!data.links || data.links.length === 0) {
            chartContainer.innerHTML = `<div class="empty-state"><p>Aucune donnée trouvée pour cette sélection.</p></div>`;
            return;
        }

        // Afficher le conteneur et dessiner
        chartContainer.style.display = 'block';
        drawSankey(data);

    } catch (error) {
        console.error("Erreur récupération flux:", error);
        chartContainer.innerHTML = `<div class="empty-state"><p class="error">Erreur technique lors de la récupération des données.</p></div>`;
    }
}

function updateStatsUI(stats) {
    const total = stats.total || 1; // Éviter la division par zéro

    const setStat = (id, count) => {
        const elCount = document.getElementById(`count-${id}`);
        const elPercent = document.getElementById(`percent-${id}`);
        if (elCount) elCount.textContent = count || 0;
        if (elPercent) elPercent.textContent = Math.round(((count || 0) / total) * 100) + "%";
    };

    setStat('valide', stats.valide);
    setStat('partiel', stats.partiel);
    setStat('red', stats.redoublement);
    setStat('abd', stats.abandon);

    // Mise à jour du total
    const totalEl = document.getElementById('total-students');
    if (totalEl) totalEl.textContent = stats.total || 0;

    // Afficher le statut de la promotion si disponible
    const statutEl = document.getElementById('statut-promo');
    if (statutEl && stats.statutPromo) {
        if (stats.statutPromo === 'terminée') {
            statutEl.textContent = 'Promotion terminée';
            statutEl.style.color = '#28a745';
        } else {
            statutEl.textContent = `Promotion ${stats.statutPromo}`;
            statutEl.style.color = '#17a2b8';
        }
        statutEl.style.display = 'block';
    } else if (statutEl) {
        statutEl.style.display = 'none';
    }
}

// Nouvelle fonction : Affiche la section Jury sans toucher aux chiffres
function updateStatsDisplay(formation, annee) {
    const section = document.getElementById('results-section');
    const subtitle = document.getElementById('stats-subtitle');

    if (section) {
        // Rendre la section visible
        section.style.display = 'block';

        // Mettre à jour uniquement le titre avec la formation choisie
        if (subtitle) {
            let displayFormation = formation;
            if (formation === '__ALL__') {
                displayFormation = "Tout l'IUT (Vue globale)";
            }
            subtitle.textContent = `Formation : ${displayFormation} • Année : ${annee}`;
        }
    }
}

function drawSankey(data) {
    const container = document.getElementById('sankey_chart');
    container.innerHTML = "";

    const chartData = new google.visualization.DataTable();
    chartData.addColumn('string', 'From');
    chartData.addColumn('string', 'To');
    chartData.addColumn('number', 'Nombre étudiants');

    const rows = data.links.map(link => [link.source, link.target, link.value]);
    chartData.addRows(rows);

    // Stocker les données pour l'accès au clic
    window.currentSankeyData = data;

    // Adaptation Chart Colors (Dark Mode)
    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#e0e0e0' : '#333';

    // Options du graphique avec interactivité activée
    // Hauteur calculée dynamiquement selon le nombre de liens
    const nbLinks = data.links.length;
    const hauteur = Math.max(350, Math.min(450, nbLinks * 35));
    container.style.height = hauteur + 'px';

    const options = {
        width: '100%',
        height: hauteur,
        sankey: {
            node: {
                interactivity: true,
                label: {
                    fontName: 'sans-serif',
                    fontSize: 12,
                    color: textColor
                },
                nodePadding: 25,
                width: 12,
                colors: isDarkMode ? ['#7cb342', '#fb8c00', '#039be5', '#e53935'] : undefined
            },
            link: {
                colorMode: 'gradient'
            }
        },
        backgroundColor: { fill: 'transparent' }
    };

    const chart = new google.visualization.Sankey(container);

    // --- CONFORMITE : Clic sur le diagramme Sankey ---
    google.visualization.events.addListener(chart, 'select', function () {
        const selection = chart.getSelection();
        if (selection.length > 0) {
            const item = selection[0];
            let nips = [];

            if (item.name) {
                // Click on Node: Aggregate students from all connected links
                const nodeName = item.name;
                const connectedLinks = data.links.filter(l => l.source === nodeName || l.target === nodeName);
                const nipSet = new Set();
                connectedLinks.forEach(l => {
                    if (l.students && Array.isArray(l.students)) {
                        l.students.forEach(n => nipSet.add(n));
                    }
                });
                nips = Array.from(nipSet);
                openStudentModal(item.name, null, nips);
            } else if (item.row != null) {
                // Click on Link
                // Assuming data.links matches chartData rows order (It should if added sequentially)
                // However, Google Charts might reorder? Usually simpler to look up by source/target
                const source = chartData.getValue(item.row, 0);
                const target = chartData.getValue(item.row, 1);

                const linkObj = data.links.find(l => l.source === source && l.target === target);
                if (linkObj && linkObj.students) {
                    nips = linkObj.students;
                }
                openStudentModal(source, target, nips);
            }
        }
        chart.setSelection([]);
    });



    chart.draw(chartData, options);
}

// --- FONCTIONS MODALES (Conformité) ---
// Note : La fonction d'export CSV a été retirée car non conforme au cahier des charges (V2)

async function openStudentModal(source, target, nips = null) {
    const modal = document.getElementById('student-modal');
    const listContainer = document.getElementById('student-list-container');
    const modalTitle = document.getElementById('modal-title');
    const formation = document.getElementById("formation").value;
    const annee = document.getElementById("annee").value;
    // const regime = document.querySelector('input[name="regime"]:checked').value;
    // const status = document.getElementById('filter-status').value;
    // Filters are implicit in the NIP list now, but we might pass them for context if needed.

    if (!modal) return;
    modal.style.display = 'flex'; // Centrage via Flexbox (voir styles injectés)

    // Reset data (si nécessaire)

    // Fonction de sécurité anti-XSS
    const escapeHtml = (text) => {
        if (!text && text !== 0) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    if (target) {
        modalTitle.textContent = `Flux : ${source} ➔ ${target}`;
    } else {
        modalTitle.textContent = `Étudiants : ${source}`;
    }

    listContainer.innerHTML = '<p style="text-align:center;">Chargement...</p>';

    try {
        const response = await fetch('api/recuperer_etudiants_par_flux.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nips: nips,
                formation: formation,
                annee: annee,
                source: source,
                target: target
            })
        });
        const data = await response.json();

        if (!data.students || data.students.length === 0) {
            let debugMsg = '';
            if (data.debug) {
                debugMsg = `<br><small style="color:#888;">Année recherchée: ${escapeHtml(data.debug.annee_recherchee)}, Candidats trouvés: ${escapeHtml(data.debug.nb_candidats)}</small>`;
            }
            listContainer.innerHTML = `<p style="text-align:center; color:#666;">Aucun étudiant trouvé pour ce flux.${debugMsg}<br><small>(Les données peuvent ne pas être disponibles pour cette année/semestre)</small></p>`;
            return;
        }

        // Afficher le nombre total d'étudiants
        let html = `<p style="text-align:center; margin-bottom:1rem; font-weight:bold;">${data.students.length} étudiant(s) trouvé(s)</p>`;

        // En-tête du tableau
        html += `<div class="student-item" style="font-weight:bold; background:#f0f0f0; border-radius:4px;">
            <span style="min-width:30px;">#</span>
            <span style="flex:2;">Identifiant (anonymisé)</span>
            <span style="flex:1;">Formation</span>
            <span style="min-width:50px;">Sem.</span>
            <span style="min-width:80px;">Décision</span>
            <span style="min-width:80px;">Action</span>
        </div>`;

        // Helper pour le style inline (Couleurs identiques au Bilan)
        const getDecisionStyle = (decision) => {
            const d = (decision || '').toUpperCase();

            // Vert Prononcé : Diplômé, Admis (#28a745 - Vert du Bilan)
            if (d.includes('DIPL') || d === 'ADM' || d === 'ADSUP' || d === 'CMP') {
                return 'background-color: #28a745 !important; color: #ffffff !important; border: 1px solid #28a745;';
            }
            // Jaune Orangé : En cours (#ffc107 - Jaune du Bilan) - Texte noir
            if (d.includes('EN COURS') || !d) {
                return 'background-color: #ffc107 !important; color: #1f2d3d !important; border: 1px solid #ffc107;';
            }
            // Rouge : Redoublement, Ajourné (#dc3545 - Rouge du Bilan)
            if (d === 'RED' || d === 'AJ' || d === 'ATJ' || d.includes('REDOUB')) {
                return 'background-color: #dc3545 !important; color: #ffffff !important; border: 1px solid #dc3545;';
            }
            // Gris : Abandon, Défaillant (#6c757d - Gris du Bilan)
            if (d === 'DEF' || d === 'DEM' || d === 'NAR' || d.includes('ABAND')) {
                return 'background-color: #6c757d !important; color: #ffffff !important; border: 1px solid #6c757d;';
            }
            // Bleu (Défaut)
            return 'background-color: #17a2b8 !important; color: #ffffff !important; border: 1px solid #17a2b8;';
        };

        data.students.forEach((etu, index) => {
            const safeDecision = escapeHtml(etu.decision || 'N/A');
            const safeNip = escapeHtml(etu.nip);
            const rawShortNip = etu.nip.length > 16 ? etu.nip.substr(0, 6) + '...' + etu.nip.substr(-6) : etu.nip;
            const safeShortNip = escapeHtml(rawShortNip);

            const rawShortFormation = etu.formation ? (etu.formation.length > 20 ? etu.formation.substr(0, 18) + '...' : etu.formation) : 'N/A';
            const safeFormation = escapeHtml(etu.formation || '');
            const safeShortFormation = escapeHtml(rawShortFormation);

            const safeSemestre = escapeHtml(etu.semestre || '-');
            const safeScodocId = etu.scodoc_id ? String(etu.scodoc_id).replace(/'/g, "\\'") : '';

            const linkHtml = etu.scodoc_id
                ? `<a href="#" onclick="alert('Fiche ScoDoc ID: ${safeScodocId}'); return false;" class="scodoc-link">Fiche</a>`
                : '<span style="color:#999;">-</span>';

            const styleColor = getDecisionStyle(etu.decision);
            // Style de base (padding, radius) + Couleur Vives
            const finalStyle = `padding: 4px 8px; border-radius: 4px; font-weight: 600; display: inline-block; min-width: 90px; text-align: center; font-size: 0.85rem; ${styleColor}`;

            html += `<div class="student-item">
                <span style="color:#888; min-width:30px;">${index + 1}.</span>
                <span class="student-nip" title="${safeNip}" style="flex:2;">${safeShortNip}</span>
                <span style="flex:1; font-size:0.8rem; color:#666;" title="${safeFormation}">${safeShortFormation}</span>
                <span style="min-width:50px; font-size:0.8rem; color:#888;">${safeSemestre}</span>
                <span class="student-decision" style="${finalStyle}">${safeDecision}</span>
                <span style="min-width:80px;">${linkHtml}</span>
            </div>`;
        });
        listContainer.innerHTML = html;
    } catch (error) {
        console.error("Erreur modal:", error);
        listContainer.innerHTML = `<p class="error">Erreur technique.</p>`;
    }
}



// Fermeture modale
window.onclick = function (event) {
    const modal = document.getElementById('student-modal');
    if (event.target == modal) modal.style.display = "none";
}
document.addEventListener('DOMContentLoaded', () => {
    const closeBtn = document.querySelector('.close-modal');
    if (closeBtn) closeBtn.onclick = () => document.getElementById('student-modal').style.display = "none";
});
