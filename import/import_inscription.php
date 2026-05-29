<?php
require_once __DIR__ . '/../config/database.php';

$files = glob(__DIR__ . '/../json/decisions_jury_*.json');

if (empty($files)) {
    die("Aucun fichier decisions_jury_*.json trouvé");
}

$sql = "INSERT INTO inscription
        (code_nip, id_formsemestre, decision_jury, etat_inscription)
        VALUES
        (:code_nip, :id_formsemestre, :decision_jury, :etat_inscription)
        ON DUPLICATE KEY UPDATE
        decision_jury = VALUES(decision_jury),
        etat_inscription = VALUES(etat_inscription)";

$stmt = $pdo->prepare($sql);
$nb = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (!preg_match('/fs_(\d+)/i', $filename, $matches)) {
        continue;
    }

    $idFormsemestre = (int) $matches[1];
    $data = json_decode(file_get_contents($file), true);

    foreach ($data as $etu) {
        $codeNip = $etu['code_nip'] ?? null;

        if (!$codeNip) {
            continue;
        }

        $decisionJury = $etu['semestre']['code'] ?? null;

        $stmt->execute([
            ':code_nip' => $codeNip,
            ':id_formsemestre' => $idFormsemestre,
            ':decision_jury' => $decisionJury,
            ':etat_inscription' => $etu['etat'] ?? null
        ]);

        $nb++;
    }
}

echo "Import inscriptions terminé : $nb";