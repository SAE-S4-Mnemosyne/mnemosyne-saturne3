<?php
require_once __DIR__ . '/../config/database.php';

$jsonPath = __DIR__ . '/../json/departements.json';

if (!file_exists($jsonPath)) {
    die("Fichier departements.json introuvable");
}

$departements = json_decode(file_get_contents($jsonPath), true);

$sql = "INSERT INTO departement (id_dept, acronyme, nom_complet)
        VALUES (:id_dept, :acronyme, :nom_complet)
        ON DUPLICATE KEY UPDATE
        acronyme = VALUES(acronyme),
        nom_complet = VALUES(nom_complet)";

$stmt = $pdo->prepare($sql);
$nb = 0;

foreach ($departements as $dept) {
    $stmt->execute([
        ':id_dept' => $dept['id'],
        ':acronyme' => $dept['acronym'] ?? null,
        ':nom_complet' => $dept['dept_name'] ?? null
    ]);
    $nb++;
}

echo "Import départements terminé : $nb";