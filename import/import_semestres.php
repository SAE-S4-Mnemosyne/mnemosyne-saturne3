<?php
// import_semestres.php

// 1. Connexion à la base
require_once __DIR__ . '/connectDB_alwaysdata.php';

// 2. Dossier où se trouvent les fichiers formsemestres_*.json
$semestresDir = __DIR__ . '/SAE_json';

// 3. Récupérer la liste de tous les fichiers formsemestres_*.json
$pattern = $semestresDir . '/formsemestres_*.json';
$files = glob($pattern);

if (empty($files)) {
    die("Aucun fichier JSON trouvé avec le pattern : $pattern\n");
}

echo "Nombre de fichiers formsemestres trouvés : " . count($files) . "\n";

// 4. Préparer la requête d'INSERT/UPDATE
// Rappel de la table :
//
// CREATE TABLE Semestre_Instance (
//     id_formsemestre integer PRIMARY KEY,
//     id_formation integer,
//     annee_scolaire integer,
//     numero_semestre integer,
//     modalite varchar,
//     date_debut date,
//     date_fin date,
//     FOREIGN KEY (id_formation) REFERENCES Formation(id_formation)
// );

$sql = "INSERT INTO Semestre_Instance (
            id_formsemestre,
            id_formation,
            annee_scolaire,
            numero_semestre,
            modalite,
            date_debut,
            date_fin
        ) VALUES (
            :id_formsemestre,
            :id_formation,
            :annee_scolaire,
            :numero_semestre,
            :modalite,
            :date_debut,
            :date_fin
        )
        ON DUPLICATE KEY UPDATE
            id_formation   = VALUES(id_formation),
            annee_scolaire = VALUES(annee_scolaire),
            numero_semestre= VALUES(numero_semestre),
            modalite       = VALUES(modalite),
            date_debut     = VALUES(date_debut),
            date_fin       = VALUES(date_fin)";

$stmt = $pdo->prepare($sql);

$totalFichiers    = 0;
$totalSemestres   = 0;

foreach ($files as $filepath) {
    $totalFichiers++;
    $filename = basename($filepath);
    echo "Traitement du fichier : $filename\n";

    // 5. Lire et décoder le JSON
    $jsonContent = file_get_contents($filepath);
    $data = json_decode($jsonContent, true);

    if ($data === null) {
        echo "  -> Erreur de décodage JSON : " . json_last_error_msg() . "\n";
        continue;
    }

    // Chaque élément de $data est un formsemestre
    foreach ($data as $sem) {

        // id_formsemestre : on privilégie formsemestre_id, sinon id
        $idFormsemestre = $sem['formsemestre_id'] ?? $sem['id'] ?? null;
        if ($idFormsemestre === null) {
            // Si pour une raison quelconque on n'a pas d'ID, on saute
            continue;
        }

        // id_formation : depuis formation_id
        $idFormation = $sem['formation_id'] ?? null;

        // annee_scolaire : directement dans le JSON
        $anneeScolaire = $sem['annee_scolaire'] ?? null;

        // numero_semestre : semestre_id
        $numeroSemestre = $sem['semestre_id'] ?? null;

        // modalite (FI, FA, FAP, etc.)
        $modalite = $sem['modalite'] ?? null;

        // dates : on utilise date_debut_iso / date_fin_iso si présents
        // sinon on pourrait convertir date_debut/date_fin (JJ/MM/AAAA),
        // mais dans ton exemple les *_iso existent.
        $dateDebut = $sem['date_debut_iso'] ?? null;
        $dateFin   = $sem['date_fin_iso'] ?? null;

        $stmt->execute([
            ':id_formsemestre' => $idFormsemestre,
            ':id_formation'    => $idFormation,
            ':annee_scolaire'  => $anneeScolaire,
            ':numero_semestre' => $numeroSemestre,
            ':modalite'        => $modalite,
            ':date_debut'      => $dateDebut,
            ':date_fin'        => $dateFin,
        ]);

        $totalSemestres++;
    }
}

echo "\nImport Semestre_Instance terminé.\n";
echo "Fichiers traités : $totalFichiers\n";
echo "Semestres insérés/mis à jour : $totalSemestres\n";
