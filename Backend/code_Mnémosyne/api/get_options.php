<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer les formations (distinct titre)
    $stmt = $pdo->query("SELECT DISTINCT titre FROM FORMATION ORDER BY titre");
    $formations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Récupérer les années scolaires (distinct annee_scolaire)
    $stmt = $pdo->query("SELECT DISTINCT annee_scolaire FROM SEMESTRE_INSTANCE ORDER BY annee_scolaire DESC");
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'formations' => $formations,
        'annees' => $annees
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
