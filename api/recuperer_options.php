<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer UNIQUEMENT les formations qui ont des inscriptions
    $stmt = $pdo->query("
        SELECT DISTINCT f.titre 
        FROM Formation f
        JOIN Semestre_Instance si ON f.id_formation = si.id_formation
        JOIN Inscription i ON si.id_formsemestre = i.id_formsemestre
        WHERE f.titre IS NOT NULL AND f.titre != ''
        ORDER BY f.titre
    ");
    $formations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Nettoyer les noms de formations (supprimer espaces multiples)
    // Fonction de normalisation (identique à celle des autres fichiers)

    function normaliseFormation($titre) {
        $titre = trim($titre);
        
        // 1. Remplacer le nom long par "BUT" pour simplifier
        $titre = preg_replace('/Bachelor\s+Universitaire\s+de\s+Technologie/ui', 'BUT', $titre);
        
        // 3. Identification par mots-clés (Ordre IMPORTANT)
        
        // GEII : "Electrique" ou "Industrielle" ou "GEII"
        if (preg_match('/(Electrique|Industrielle|GEII|G\.E\.I\.I)/ui', $titre)) return 'BUT GEII';
        
        // CJ : "Juridique" ou "CJ"
        if (preg_match('/(Juridique|CJ)/ui', $titre)) return 'BUT CJ';
        
        // INFO : "Informatique" ou "INFO" (après GEII pour ne pas capter "Info Indus")
        if (preg_match('/(Informatique|INFO)/ui', $titre)) return 'BUT INFO';
        
        // R&T : "Réseaux" ou "Télécom" ou "R&T"
        if (preg_match('/(R[eé]seaux|T[eé]l[eé]com|R\&T|R\.T)/ui', $titre)) return 'BUT R&T';
        
        // GEA : "Gestion" ou "GEA"
        if (preg_match('/(Gestion|GEA|G\.E\.A)/ui', $titre)) return 'BUT GEA';
        
        // SD : "Données" ou "Daniel" (si présent) ou "STID" ou "SD"
        if (preg_match('/(Donn[ée]es|STID|SD)/ui', $titre)) return 'BUT SD';
        
        // TC : "Commercialisation" ou "TC"
        if (preg_match('/(Commercialisation|TC)/ui', $titre)) return 'BUT TC';

        // Si "BUT" tout seul ou vide, on ignore
        if ($titre === 'BUT' || $titre === '') {
            return null;
        }
        
        return $titre;
    }

    // Nettoyer et dédoubler
    $uniqueFormations = [];
    foreach ($formations as $f) {
        $clean = trim(preg_replace('/\s+/', ' ', $f));
        $normalized = normaliseFormation($clean);
        
        // Si normalized est null (ex: le generic "Bachelor..."), on ne l'ajoute pas
        if ($normalized && !in_array($normalized, $uniqueFormations)) {
            $uniqueFormations[] = $normalized;
        }
    }
    sort($uniqueFormations);
    $formations = $uniqueFormations;

    // 2. Récupérer TOUTES les années définies (même sans inscriptions pour le moment)
    $stmt = $pdo->query("
        SELECT DISTINCT si.annee_scolaire 
        FROM Semestre_Instance si
        WHERE si.annee_scolaire IS NOT NULL
        AND si.annee_scolaire != '2020'
        ORDER BY si.annee_scolaire DESC
    ");
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'formations' => $formations,
        'annees' => $annees
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
