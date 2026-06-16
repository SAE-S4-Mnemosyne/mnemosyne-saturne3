<?php
/**
 * API Bilan -- Renvoie les statistiques globales de la cohorte.
 * Requetes SQL basees sur le travail de Zoubida (requetes_bilan.sql).
 *
 * Parametres GET optionnels :
 *   - formation : titre de la formation (filtre)
 *   - annee     : annee scolaire (filtre)
 *
 * Retour JSON :
 *   { admis_ajournes, abandons, reorientations, effectifs, competences }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';

try {
    $pdo = Database::getInstance();

    $formation = $_GET['formation'] ?? null;
    $annee     = $_GET['annee']     ?? null;

    $resultat = [];

    // ---------------------------------------------------------------
    // 1. Admis vs ajournes par semestre
    // ---------------------------------------------------------------
    $sql1 = "
        SELECT
            si.numero_semestre,
            COUNT(*) AS total_decisions,
            SUM(CASE WHEN i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ') THEN 1 ELSE 0 END) AS nb_admis,
            SUM(CASE WHEN i.decision_jury IN ('RED','AJ','ATJ') THEN 1 ELSE 0 END) AS nb_ajournes,
            ROUND(
                100 * SUM(CASE WHEN i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ') THEN 1 ELSE 0 END)
                / NULLIF(COUNT(*), 0), 1
            ) AS pct_admis,
            ROUND(
                100 * SUM(CASE WHEN i.decision_jury IN ('RED','AJ','ATJ') THEN 1 ELSE 0 END)
                / NULLIF(COUNT(*), 0), 1
            ) AS pct_ajournes
        FROM Inscription i
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN Formation f ON si.id_formation = f.id_formation
        WHERE i.decision_jury IS NOT NULL
    ";
    $params1 = [];
    if ($formation) {
        $sql1 .= " AND f.titre LIKE :formation";
        $params1[':formation'] = '%' . $formation . '%';
    }
    if ($annee) {
        $sql1 .= " AND si.annee_scolaire = :annee";
        $params1[':annee'] = $annee;
    }
    $sql1 .= " GROUP BY si.numero_semestre ORDER BY si.numero_semestre";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute($params1);
    $resultat['admis_ajournes'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------------
    // 2. Taux d'abandon (present une annee, absent la suivante, hors diplomes)
    // ---------------------------------------------------------------
    $sql2 = "
        SELECT
            pa.annee_ref,
            COUNT(*) AS effectif,
            SUM(CASE WHEN pn.code_nip IS NULL AND dip.code_nip IS NULL THEN 1 ELSE 0 END) AS nb_abandons,
            ROUND(
                100 * SUM(CASE WHEN pn.code_nip IS NULL AND dip.code_nip IS NULL THEN 1 ELSE 0 END)
                / NULLIF(COUNT(*), 0), 1
            ) AS taux_abandon
        FROM (
            SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee_ref
            FROM Inscription i
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        ) pa
        LEFT JOIN (
            SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee_ref
            FROM Inscription i
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        ) pn ON pn.code_nip = pa.code_nip AND pn.annee_ref = pa.annee_ref + 1
        LEFT JOIN (
            SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee_ref
            FROM Inscription i
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
            WHERE si.numero_semestre IN (5,6) AND i.decision_jury IN ('ADM','ADSUP','CMP','ADJ')
        ) dip ON dip.code_nip = pa.code_nip AND dip.annee_ref = pa.annee_ref
        WHERE pa.annee_ref < (SELECT MAX(CAST(LEFT(annee_scolaire, 4) AS UNSIGNED)) FROM Semestre_Instance)
        GROUP BY pa.annee_ref
        ORDER BY pa.annee_ref
    ";
    $stmt2 = $pdo->query($sql2);
    $resultat['abandons'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------------
    // 3. Taux de reorientation (changement de formation ou entree directe en 2e/3e annee)
    // ---------------------------------------------------------------
    $sql3 = "
        SELECT
            COUNT(*) AS nb_etudiants,
            SUM(reoriente) AS nb_reorientes,
            ROUND(100 * SUM(reoriente) / NULLIF(COUNT(*), 0), 1) AS taux_reorientation
        FROM (
            SELECT
                i.code_nip,
                CASE WHEN COUNT(DISTINCT si.id_formation) > 1
                          OR (SUM(CASE WHEN si.numero_semestre IN (1,2) THEN 1 ELSE 0 END) = 0
                              AND SUM(CASE WHEN si.numero_semestre IN (3,4,5,6) THEN 1 ELSE 0 END) > 0)
                     THEN 1 ELSE 0 END AS reoriente
            FROM Inscription i
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
            GROUP BY i.code_nip
        ) t
    ";
    $stmt3 = $pdo->query($sql3);
    $resultat['reorientations'] = $stmt3->fetch(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------------
    // 4. Effectifs par formation
    // ---------------------------------------------------------------
    $sql4 = "
        SELECT
            f.titre AS formation,
            COUNT(DISTINCT i.code_nip) AS nb_etudiants,
            COUNT(*) AS nb_inscriptions
        FROM Inscription i
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN Formation f ON si.id_formation = f.id_formation
        GROUP BY f.id_formation, f.titre
        ORDER BY nb_etudiants DESC
    ";
    $stmt4 = $pdo->query($sql4);
    $resultat['effectifs'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------------
    // 5. Repartition par competences validees (ex: 4/6, 5/6, 6/6)
    // ---------------------------------------------------------------
    $sql5 = "
        SELECT
            nb_total AS competences_total,
            nb_validees AS competences_validees,
            COUNT(*) AS nb_etudiants,
            ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (), 1) AS pct_etudiants
        FROM (
            SELECT
                i.id_inscription,
                COUNT(*) AS nb_total,
                SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP') THEN 1 ELSE 0 END) AS nb_validees
            FROM Inscription i
            JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
            WHERE i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ')
            GROUP BY i.id_inscription
        ) t
        GROUP BY nb_total, nb_validees
        ORDER BY nb_total, nb_validees
    ";
    $stmt5 = $pdo->query($sql5);
    $resultat['competences'] = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultat);

} catch (PDOException $e) {
    error_log("Erreur API bilan : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recuperation du bilan.']);
}
