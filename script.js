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
});

// Gestion du Menu Hamburger
function toggleMenu() {
    const nav = document.getElementById('nav-menu');
    nav.classList.toggle('active');
}

// Gestion du Thème (Dark/Light)
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const body = document.body;
    const btn = document.getElementById('theme-toggle');

    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        if (btn) btn.textContent = '☀️';
    } else {
        body.classList.remove('dark-mode');
        if (btn) btn.textContent = '🌙';
    }
}

function toggleTheme() {
    const body = document.body;
    const btn = document.getElementById('theme-toggle');
    body.classList.toggle('dark-mode');

    if (body.classList.contains('dark-mode')) {
        localStorage.setItem('theme', 'dark');
        if (btn) btn.textContent = '☀️';
    } else {
        localStorage.setItem('theme', 'light');
        if (btn) btn.textContent = '🌙';
    }
}

// Chargement des Options
async function loadOptions() {
    try {
        const response = await fetch('api/get_options.php'); // Fixed path: was ../Backend/code_Mnémosyne/api/get_options.php
        const data = await response.json();
        populateSelects(data);

    } catch (error) {
        console.warn("API non accessible ou BDD vide, chargement des données de secours...", error);
        // Données de Fallback (Secours) pour que l'interface fonctionne
        const fallbackData = {
            formations: ["BUT Informatique", "BUT GEA", "BUT TC", "BUT GEII"],
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
        optAll.textContent = "📊 Tout l'IUT (Vue globale)";
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

    // Récupération des éléments d'interface (Loader, Chart)
    const loader = document.getElementById("chart-loader");
    const chartContainer = document.getElementById("sankey_chart");

    if (!formation || !annee) {
        alert("Veuillez sélectionner une formation et une année.");
        return;
    }

    // Afficher le loader
    if (loader) loader.style.display = 'flex';

    // 1. Afficher la section Jury (Juste le visuel, sans valeurs dynamiques)
    updateStatsDisplay(formation, annee);

    try {
        const url = `api/get_flow_data.php?formation=${encodeURIComponent(formation)}&annee=${encodeURIComponent(annee)}`; // Fixed path: was ../Backend/code_Mnémosyne/api/get_flow_data.php
        const response = await fetch(url);
        const data = await response.json();

        // Cacher le loader
        if (loader) loader.style.display = 'none';

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
        if (loader) loader.style.display = 'none';
        chartContainer.innerHTML = `<div class="empty-state"><p class="error">Erreur technique lors de la récupération des données.</p></div>`;
    }
}

function updateStatsUI(stats) {
    const total = stats.total || 1; // Avoid division by zero

    const setStat = (id, count) => {
        const elCount = document.getElementById(`count-${id}`);
        const elPercent = document.getElementById(`percent-${id}`);
        if (elCount) elCount.textContent = count;
        if (elPercent) elPercent.textContent = Math.round((count / total) * 100) + "%";
    };

    setStat('valide', stats.valide);
    setStat('partiel', stats.partiel);
    setStat('red', stats.redoublement);
    setStat('abd', stats.abandon);

    // Update total
    const totalEl = document.getElementById('total-students');
    if (totalEl) totalEl.textContent = stats.total || 0;
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
            subtitle.textContent = `Formation : ${formation} • Année : ${annee}`;
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

    // Adaptation Chart Colors (Dark Mode)
    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#e0e0e0' : '#333';

    // Options du graphique
    const options = {
        width: '100%',
        sankey: {
            node: {
                label: {
                    fontName: 'Inter',
                    fontSize: 14,
                    color: textColor
                },
                nodePadding: 30,
                width: 20,
                colors: isDarkMode ? ['#7cb342', '#fb8c00', '#039be5', '#e53935'] : undefined
            },
            link: {
                colorMode: 'gradient'
            }
        },
        backgroundColor: { fill: 'transparent' }
    };

    const chart = new google.visualization.Sankey(container);
    chart.draw(chartData, options);
}
