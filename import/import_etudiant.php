<?php
// import_etudiant.php

// 1. Connexion à la base
require_once __DIR__ . '/connectDB_alwaysdata.php';

// 2. Dossier où se trouvent les fichiers decisions_jury_*.json
$decisionsDir = __DIR__ . '/SAE_json';

// 3. Récupérer la liste de tous les fichiers decisions_jury_*.json
$pattern = $decisionsDir . '/decisions_jury_*.json';
$files = glob($pattern);

if (empty($files)) {
    die("Aucun fichier JSON trouvé avec le pattern : $pattern\n");
}

echo "Nombre de fichiers trouvés : " . count($files) . "\n";

// 4. Préparer la requête pour insérer / mettre à jour un étudiant
$sql = "INSERT INTO Etudiant (code_nip, code_ine, etudid_scodoc)
        VALUES (:code_nip, :code_ine, :etudid_scodoc)
        ON DUPLICATE KEY UPDATE
            code_ine = VALUES(code_ine),
            etudid_scodoc = VALUES(etudid_scodoc)";

$stmt = $pdo->prepare($sql);

// Pour éviter de traiter 10 fois le même étudiant dans la même exécution
$seenNip = [];

$totalFichiers = 0;
$totalEtudiantsLus = 0;
$totalEtudiantsInseres = 0;

foreach ($files as $filepath) {
    $totalFichiers++;
    echo "Traitement du fichier : $filepath\n";

    $jsonContent = file_get_contents($filepath);
    $data = json_decode($jsonContent, true);

    if ($data === null) {
        echo "  -> Erreur JSON dans $filepath : " . json_last_error_msg() . "\n";
        continue;
    }

    // Chaque élément de $data = un étudiant pour un formsemestre donné
    foreach ($data as $etudiant) {
        $totalEtudiantsLus++;

        $codeNip = $etudiant['code_nip'] ?? null;
        $codeIne = $etudiant['code_ine'] ?? null;
        $etudid  = $etudiant['etudid'] ?? null;

        if (empty($codeNip)) {
            // Si jamais un enregistrement n'a pas de code_nip, on le saute
            continue;
        }

        // Si on a déjà vu ce code_nip dans cette exécution, on peut sauter
        if (isset($seenNip[$codeNip])) {
            continue;
        }

        $seenNip[$codeNip] = true;

        $stmt->execute([
            ':code_nip'      => $codeNip,
            ':code_ine'      => $codeIne,
            ':etudid_scodoc' => $etudid,
        ]);

        $totalEtudiantsInseres++;
    }
}

echo "\nImport Etudiant terminé.\n";
echo "Fichiers traités : $totalFichiers\n";   // sensé être égal à 204
echo "Enregistrements JSON lus : $totalEtudiantsLus\n";
echo "Étudiants distincts insérés/MAJ : $totalEtudiantsInseres\n";
