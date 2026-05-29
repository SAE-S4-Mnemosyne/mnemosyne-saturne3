<?php
require_once __DIR__ . '/../config/database.php';

$files = glob(__DIR__ . '/../json/decisions_jury_*.json');

if (empty($files)) {
    die("Aucun fichier decisions_jury_*.json trouvé");
}

$sql = "INSERT INTO etudiant (code_nip, code_ine, etudid_scodoc)
        VALUES (:code_nip, :code_ine, :etudid_scodoc)
        ON DUPLICATE KEY UPDATE
        code_ine = VALUES(code_ine),
        etudid_scodoc = VALUES(etudid_scodoc)";

$stmt = $pdo->prepare($sql);
$seen = [];
$nb = 0;

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);

    foreach ($data as $etu) {
        $codeNip = $etu['code_nip'] ?? null;

        if (!$codeNip || isset($seen[$codeNip])) {
            continue;
        }

        $seen[$codeNip] = true;

        $stmt->execute([
            ':code_nip' => $codeNip,
            ':code_ine' => $etu['code_ine'] ?? null,
            ':etudid_scodoc' => $etu['etudid'] ?? null
        ]);

        $nb++;
    }
}

echo "Import étudiants terminé : $nb";