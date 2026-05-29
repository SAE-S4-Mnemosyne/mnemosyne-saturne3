<?php
require_once __DIR__ . '/../config/database.php';

$files = glob(__DIR__ . '/../json/decisions_jury_*.json');

if (empty($files)) {
    die("Aucun fichier decisions_jury_*.json trouvé");
}

$sql = "INSERT INTO decision_annuelle
        (code_nip, annee_scolaire, decision)
        VALUES
        (:code_nip, :annee_scolaire, :decision)
        ON DUPLICATE KEY UPDATE
        decision = VALUES(decision)";

$stmt = $pdo->prepare($sql);
$nb = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (!preg_match('/decisions_jury_(\d{4})_/i', $filename, $matches)) {
        continue;
    }

    $anneeScolaire = $matches[1];
    $data = json_decode(file_get_contents($file), true);

    foreach ($data as $etu) {
        $codeNip = $etu['code_nip'] ?? null;
        $decision = $etu['annee']['code'] ?? null;

        if (!$codeNip) {
            continue;
        }

        $stmt->execute([
            ':code_nip' => $codeNip,
            ':annee_scolaire' => $anneeScolaire,
            ':decision' => $decision
        ]);

        $nb++;
    }
}

echo "Import décisions annuelles terminé : $nb";