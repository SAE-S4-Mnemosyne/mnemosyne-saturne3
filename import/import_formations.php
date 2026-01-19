<?php
// import_formations.php

// 1. Connexion à la base
require_once __DIR__ . '/connectDB_alwaysdata.php';

// 2. Chemin vers le fichier JSON des formations
$jsonPath = __DIR__ . '/SAE_json/formations.json';

if (!file_exists($jsonPath)) {
    die("Fichier formations.json introuvable à l'emplacement : $jsonPath\n");
}

// 3. Lecture et décodage du JSON
$jsonContent = file_get_contents($jsonPath);
$formations = json_decode($jsonContent, true);

if ($formations === null) {
    die("Erreur de décodage JSON : " . json_last_error_msg() . "\n");
}

// 4. Préparer la requête d'INSERT/UPDATE
// Rappel de ta table :
// CREATE TABLE Formation (
//     id_formation integer PRIMARY KEY,
//     id_dept integer,
//     code_scodoc varchar,
//     titre varchar,
//     FOREIGN KEY (id_dept) REFERENCES Departement(id_dept)
// );

$sql = "INSERT INTO Formation (id_formation, id_dept, code_scodoc, titre)
        VALUES (:id_formation, :id_dept, :code_scodoc, :titre)
        ON DUPLICATE KEY UPDATE
            id_dept    = VALUES(id_dept),
            code_scodoc = VALUES(code_scodoc),
            titre      = VALUES(titre)";

$stmt = $pdo->prepare($sql);

$nbLignes = 0;

foreach ($formations as $form) {

    // Dans formations.json, tu as à la fois 'id' et 'formation_id'
    // Ils ont la même valeur: on choisit 'id' comme id_formation.
    $idFormation = $form['id'];

    // Département de rattachement (clé étrangère vers Departement)
    $idDept = $form['dept_id'];

    // Code de la formation dans ScoDoc / API
    $codeScodoc = $form['formation_code'];

    // Titre: on privilégie titre_officiel si présent, sinon titre
    $titre = !empty($form['titre_officiel'])
        ? $form['titre_officiel']
        : $form['titre'];

    $stmt->execute([
        ':id_formation' => $idFormation,
        ':id_dept'      => $idDept,
        ':code_scodoc'  => $codeScodoc,
        ':titre'        => $titre,
    ]);

    $nbLignes++;
}

echo "Import formations terminé : $nbLignes lignes traitées à partir de formations.json.\n";
