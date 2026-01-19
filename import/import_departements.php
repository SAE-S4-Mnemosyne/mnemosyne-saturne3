<?php
// import_departements.php

// 1. Connexion à la base
require_once __DIR__ . '/connectDB_alwaysdata.php';

// 2. Chemin vers le JSON
$jsonPath = __DIR__ . '/SAE_json/departements.json';

if (!file_exists($jsonPath)) {
    die("Fichier departements.json introuvable à l'emplacement : $jsonPath\n");
}

// 3. Lecture du fichier
$jsonContent = file_get_contents($jsonPath);
$departements = json_decode($jsonContent, true);

if ($departements === null) {
    die("Erreur de décodage JSON : " . json_last_error_msg() . "\n");
}

// 4. Préparer la requête d'insert/update
$sql = "INSERT INTO Departement (id_dept, acronyme, nom_complet)
        VALUES (:id_dept, :acronyme, :nom_complet)
        ON DUPLICATE KEY UPDATE
            acronyme = VALUES(acronyme),
            nom_complet = VALUES(nom_complet)";

$stmt = $pdo->prepare($sql);

$nb = 0;

// 5. Boucler sur les départements du JSON
foreach ($departements as $dept) {
    $stmt->execute([
        ':id_dept'     => $dept['id'],         // JSON.id -> Departement.id_dept
        ':acronyme'    => $dept['acronym'],    // JSON.acronym -> acronyme
        ':nom_complet' => $dept['dept_name'],  // JSON.dept_name -> nom_complet
    ]);
    $nb++;
}

// 6. Petit message en console
echo "Import terminé : $nb départements traités.\n";
