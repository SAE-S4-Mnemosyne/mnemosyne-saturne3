<?php
require_once __DIR__ . '/../config/database.php';

$files = glob(__DIR__ . '/../json/decisions_jury_*.json');

if (empty($files)) {
    die("Aucun fichier decisions_jury_*.json trouvé");
}

$find = $pdo->prepare(
    "SELECT id_inscription
     FROM inscription
     WHERE code_nip = :code_nip
     AND id_formsemestre = :id_formsemestre
     LIMIT 1"
);

$insert = $pdo->prepare(
    "INSERT INTO resultat_competence
     (id_inscription, numero_competence, code_decision, moyenne)
     VALUES
     (:id_inscription, :numero_competence, :code_decision, :moyenne)
     ON DUPLICATE KEY UPDATE
     code_decision = VALUES(code_decision),
     moyenne = VALUES(moyenne)"
);

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

        if (!$codeNip || empty($etu['rcues'])) {
            continue;
        }

        $find->execute([
            ':code_nip' => $codeNip,
            ':id_formsemestre' => $idFormsemestre
        ]);

        $idInscription = $find->fetchColumn();

        if (!$idInscription) {
            continue;
        }

        $numero = 1;

        foreach ($etu['rcues'] as $rcue) {
            $insert->execute([
                ':id_inscription' => $idInscription,
                ':numero_competence' => $numero,
                ':code_decision' => $rcue['code'] ?? null,
                ':moyenne' => $rcue['moy'] ?? null
            ]);

            $numero++;
            $nb++;
        }
    }
}

echo "Import résultats compétences terminé : $nb";