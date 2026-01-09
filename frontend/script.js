// Charger la librairie Google Charts
google.charts.load('current', { 'packages': ['sankey'] });
// google.charts.setOnLoadCallback(drawChart); 

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

// --- Gestion du Menu Hamburger ---
function toggleMenu() {
    const nav = document.getElementById('nav-menu');
    nav.classList.toggle('active');
}

// --- Gestion du Thème (Dark/Light) ---
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

// --- Chargement des Options ---
async function loadOptions() {
    try {
        const response = await fetch('../Backend/code_Mnémosyne/api/get_options.php');
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
            opt.textContent = a + "/" + (parseInt(a) + 1);
            anneeSelect.appendChild(opt);
        });
    }
}

async function fetchAndDraw() {
    const formation = document.getElementById("formation").value;
    const annee = document.getElementById("annee").value;

    if (!formation || !annee) {
        alert("Veuillez sélectionner une formation et une année.");
        return;
    }

    try {
        const url = `../Backend/code_Mnémosyne/api/get_flow_data.php?formation=${encodeURIComponent(formation)}&annee=${encodeURIComponent(annee)}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.error) {
            document.getElementById("sankey_chart").innerHTML = `<p class="error">${data.error}</p>`;
            return;
        }

        if (!data.links || data.links.length === 0) {
            document.getElementById("sankey_chart").innerHTML = `<p>Aucune donnée trouvée pour cette sélection.</p>`;
            return;
        }

        drawSankey(data);

    } catch (error) {
        console.error("Erreur récupération flux:", error);
        document.getElementById("sankey_chart").innerHTML = `<p class="error">Erreur technique lors de la récupération des données.</p>`;

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

    // --- Adaptation Chart Colors (Dark Mode) ---
    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#e0e0e0' : '#333';
    const nodeColor = isDarkMode ? '#5a5a5a' : undefined; // Laisse Google choisir ou force

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
        backgroundColor: { fill: 'transparent' } // Important pour le dark mode layout
    };

    const chart = new google.visualization.Sankey(container);
    chart.draw(chartData, options);
}
