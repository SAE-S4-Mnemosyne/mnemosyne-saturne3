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
        FROM formation f
        JOIN semestre_instance si ON f.id_formation = si.id_formation
        JOIN inscription i ON si.id_formsemestre = i.id_formsemestre
        WHERE f.titre IS NOT NULL AND f.titre != ''
        ORDER BY f.titre
    ");
    $formations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Nettoyer les noms de formations (supprimer espaces multiples)
    $formations = array_map(function($f) {
        return trim(preg_replace('/\s+/', ' ', $f));
    }, $formations);
    $formations = array_unique($formations);
    $formations = array_values($formations);

    // 2. Récupérer UNIQUEMENT les années qui ont des inscriptions
    $stmt = $pdo->query("
        SELECT DISTINCT si.annee_scolaire 
        FROM semestre_instance si
        JOIN inscription i ON si.id_formsemestre = i.id_formsemestre
        WHERE si.annee_scolaire IS NOT NULL
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
