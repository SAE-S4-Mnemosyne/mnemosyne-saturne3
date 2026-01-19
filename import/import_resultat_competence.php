<?php
// import_resultat_competence.php

require_once __DIR__ . '/connectDB_alwaysdata.php';

// Dossier des decisions_jury_*.json
$decisionsDir = __DIR__ . '/SAE_json';
$pattern = $decisionsDir . '/decisions_jury_*.json';
$files = glob($pattern);

if (empty($files)) {
    die("Aucun fichier JSON trouvé avec le pattern : $pattern\n");
}

echo "Nombre de fichiers decisions_jury trouvés : " . count($files) . "\n";

// Requête pour retrouver l'id_inscription à partir de (code_nip, id_formsemestre)
$sqlFindInsc = "SELECT id_inscription
                FROM Inscription
                WHERE code_nip = :code_nip
                  AND id_formsemestre = :id_formsemestre
                LIMIT 1";
$stmtFindInsc = $pdo->prepare($sqlFindInsc);

// Requête d'INSERT dans Resultat_Competence
// id_resultat est AUTO_INCREMENT
$sqlInsertRes = "INSERT IGNORE INTO Resultat_Competence (
                     id_inscription,
                     numero_competence,
                     code_decision,
                     moyenne
                 ) VALUES (
                     :id_inscription,
                     :numero_competence,
                     :code_decision,
                     :moyenne
                 )";
$stmtInsertRes = $pdo->prepare($sqlInsertRes);

$totalFichiers   = 0;
$totalEtudiants  = 0;
$totalResultats  = 0;
$totalSansInsc   = 0;

foreach ($files as $filepath) {
    $totalFichiers++;
    $filename = basename($filepath);
    echo "Traitement du fichier : $filename\n";

    // Récupérer id_formsemestre à partir du nom de fichier
    // ex: decisions_jury_2021_fs_1041_BUT_INFO.json
    if (!preg_match('/^decisions_jury_(\d{4})_fs_(\d+)_/i', $filename, $matches)) {
        echo "  -> Nom de fichier inattendu, ignoré.\n";
        continue;
    }

    $anneeFichier   = (int) $matches[1];
    $idFormsemestre = (int) $matches[2];

    // Lire le JSON
    $jsonContent = file_get_contents($filepath);
    $data = json_decode($jsonContent, true);

    if ($data === null) {
        echo "  -> Erreur JSON : " . json_last_error_msg() . "\n";
        continue;
    }

    foreach ($data as $etudiant) {
        $totalEtudiants++;

        $codeNip = $etudiant['code_nip'] ?? null;
        if (empty($codeNip)) {
            continue;
        }

        // Retrouver l'id_inscription correspondant
        $stmtFindInsc->execute([
            ':code_nip'        => $codeNip,
            ':id_formsemestre' => $idFormsemestre,
        ]);
        $idInscription = $stmtFindInsc->fetchColumn();

        if ($idInscription === false) {
            // Si pour une raison quelconque l'inscription n'existe pas (import pas fait, etc.)
            $totalSansInsc++;
            continue;
        }

        // Vérifier qu'on a bien un tableau rcues
        if (empty($etudiant['rcues']) || !is_array($etudiant['rcues'])) {
            continue;
        }

        $numero = 1;
        foreach ($etudiant['rcues'] as $rcue) {
            $codeDecision = $rcue['code'] ?? null;
            $moyenne      = $rcue['moy']  ?? null;

            $stmtInsertRes->execute([
                ':id_inscription'   => $idInscription,
                ':numero_competence' => $numero,
                ':code_decision'    => $codeDecision,
                ':moyenne'          => $moyenne,
            ]);

            $totalResultats++;
            $numero++;
        }
    }
}

echo "\nImport Resultat_Competence terminé.\n";
echo "Fichiers traités : $totalFichiers\n";
echo "Étudiants lus : $totalEtudiants\n";
echo "Résultats insérés : $totalResultats\n";
echo "Étudiants sans inscription trouvée : $totalSansInsc\n";
