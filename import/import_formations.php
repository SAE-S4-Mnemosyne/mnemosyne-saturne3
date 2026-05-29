<?php
require_once __DIR__ . '/../config/database.php';

$jsonPath = __DIR__ . '/../json/formations.json';

if (!file_exists($jsonPath)) {
    die("Fichier formations.json introuvable");
}

$formations = json_decode(file_get_contents($jsonPath), true);

$sql = "INSERT INTO formation (id_formation, id_dept, code_scodoc, titre)
        VALUES (:id_formation, :id_dept, :code_scodoc, :titre)
        ON DUPLICATE KEY UPDATE
        id_dept = VALUES(id_dept),
        code_scodoc = VALUES(code_scodoc),
        titre = VALUES(titre)";

$stmt = $pdo->prepare($sql);
$nb = 0;

foreach ($formations as $form) {
    $stmt->execute([
        ':id_formation' => $form['id'] ?? $form['formation_id'],
        ':id_dept' => $form['dept_id'],
        ':code_scodoc' => $form['formation_code'] ?? null,
        ':titre' => $form['titre_officiel'] ?? $form['titre'] ?? null
    ]);
    $nb++;
}

echo "Import formations terminé : $nb";