<?php
/**
 * API Bilan - Decoupage des competences des admis (avec / sans dette)
 * Parametres GET : formation (titre, optionnel), annee (optionnel)
 * Renvoie la repartition validees/total des admis pour une formation/annee donnee.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';

try {
    $pdo = Database::getInstance();

    $formation = $_GET['formation'] ?? '';
    $annee     = $_GET['annee'] ?? '';

    // Filtres dynamiques (sans reutiliser de placeholder)
    $where  = [];
    $params = [];
    if ($formation !== '' && $formation !== '__ALL__') {
        $where[] = "f.titre = :formation";
        $params[':formation'] = $formation;
    }
    if ($annee !== '') {
        $where[] = "LEFT(si.annee_scolaire, 4) = LEFT(:annee, 4)";
        $params[':annee'] = $annee;
    }
    $whereSql = $where ? ('AND ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
            CONCAT(nb_validees, '/', nb_total) AS ratio,
            (nb_total - nb_validees)           AS nb_dette,
            COUNT(*)                           AS nb_etudiants,
            ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (), 1) AS pct
        FROM (
            SELECT
                i.id_inscription,
                COUNT(*) AS nb_total,
                SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP','ADJ') THEN 1 ELSE 0 END) AS nb_validees
            FROM Inscription i
            JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN Formation f ON si.id_formation = f.id_formation
            JOIN Decision_Annuelle da ON da.code_nip = i.code_nip
                                      AND LEFT(da.annee_scolaire, 4) = LEFT(si.annee_scolaire, 4)
                                      AND da.decision IN ('ADM','ADSUP','PASD','PAS1NCI','ADJ')
            WHERE 1 = 1
            $whereSql
            GROUP BY i.id_inscription
        ) t
        GROUP BY nb_total, nb_validees
        ORDER BY nb_total, nb_validees
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Synthese sans dette / avec dette
    $sansDette = 0;
    $avecDette = 0;
    foreach ($lignes as $l) {
        if ((int) $l['nb_dette'] === 0) $sansDette += (int) $l['nb_etudiants'];
        else                            $avecDette += (int) $l['nb_etudiants'];
    }

    echo json_encode([
        'formation'  => $formation,
        'annee'      => $annee,
        'repartition' => $lignes,        // detail par ratio (ex: 5/6, 4/6...)
        'sans_dette' => $sansDette,
        'avec_dette' => $avecDette,
    ]);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
