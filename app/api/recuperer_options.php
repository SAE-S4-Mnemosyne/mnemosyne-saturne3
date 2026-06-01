<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/utilitaires.php';

try {
    $pdo = Database::getInstance();

    // Recuperer les formations qui ont des inscriptions
    $stmt = $pdo->query("
        SELECT DISTINCT f.titre 
        FROM Formation f
        JOIN Semestre_Instance si ON f.id_formation = si.id_formation
        JOIN Inscription i ON si.id_formsemestre = i.id_formsemestre
        WHERE f.titre IS NOT NULL AND f.titre != ''
        ORDER BY f.titre
    ");
    $formations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Nettoyer et dedoubler via normaliseFormation() de utilitaires.php
    $uniqueFormations = [];
    foreach ($formations as $f) {
        $clean = trim(preg_replace('/\s+/', ' ', $f));
        $normalized = normaliseFormation($clean);
        
        if ($normalized && $normalized !== 'BUT' && !in_array($normalized, $uniqueFormations)) {
            $uniqueFormations[] = $normalized;
        }
    }
    sort($uniqueFormations);
    $formations = $uniqueFormations;

    // Recuperer toutes les annees
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
    error_log("Erreur recuperer_options : " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la recuperation des options.']);
}
