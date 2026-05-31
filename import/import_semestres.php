<?php
require_once __DIR__ . '/../config/database.php';

$files = glob(__DIR__ . '/../json/formsemestres_*.json');

if (empty($files)) {
    die("Aucun fichier formsemestres_*.json trouvé");
}

$sql = "INSERT INTO semestre_instance
        (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite, date_debut, date_fin)
        VALUES
        (:id_formsemestre, :id_formation, :annee_scolaire, :numero_semestre, :modalite, :date_debut, :date_fin)
        ON DUPLICATE KEY UPDATE
        id_formation = VALUES(id_formation),
        annee_scolaire = VALUES(annee_scolaire),
        numero_semestre = VALUES(numero_semestre),
        modalite = VALUES(modalite),
        date_debut = VALUES(date_debut),
        date_fin = VALUES(date_fin)";

$stmt = $pdo->prepare($sql);
$nb = 0;

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);

    foreach ($data as $sem) {
        $idFormsemestre = $sem['formsemestre_id'] ?? $sem['id'] ?? null;

        if (!$idFormsemestre) {
            continue;
        }

        $stmt->execute([
            ':id_formsemestre' => $idFormsemestre,
            ':id_formation' => $sem['formation_id'] ?? null,
            ':annee_scolaire' => $sem['annee_scolaire'] ?? null,
            ':numero_semestre' => $sem['semestre_id'] ?? null,
            ':modalite' => $sem['modalite'] ?? null,
            ':date_debut' => $sem['date_debut_iso'] ?? null,
            ':date_fin' => $sem['date_fin_iso'] ?? null
        ]);

        $nb++;
    }
}

echo "Import semestres terminé : $nb";
