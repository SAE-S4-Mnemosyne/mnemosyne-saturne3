<?php
/**
 * API pour extraire la liste detailllee des etudiants selon des regles specifiques
 * (Exemple: Etudiants ayant valide au moins 5 competences sur 6)
 *
 * Parametres GET :
 *   - formation : titre de la formation
 *   - annee : annee scolaire
 *   - regle : type de regle ('min_competences_validees')
 *   - valeur : parametre de la regle (ex: 5)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';

try {
    $pdo = Database::getInstance();

    $formation = $_GET['formation'] ?? null;
    $annee = $_GET['annee'] ?? null;
    $regle = $_GET['regle'] ?? 'min_competences_validees';
    $valeur = isset($_GET['valeur']) ? (int)$_GET['valeur'] : 5;

    if (!$formation || !$annee) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametres formation et annee requis.']);
        exit;
    }

    $resultat = [];

    if ($regle === 'min_competences_validees') {
        // Extraire les etudiants de la formation precise, ayant passe au moins 1 competence
        // et dont le nombre de competences validees est >= $valeur
        
        $sql = "
            SELECT 
                etu.code_nip,
                etu.nom,
                etu.prenom,
                i.decision_jury,
                COUNT(rc.id_resultat) AS nb_competences_evaluees,
                SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP') THEN 1 ELSE 0 END) AS nb_competences_validees
            FROM Etudiant etu
            JOIN Inscription i ON etu.code_nip = i.code_nip
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN Formation f ON si.id_formation = f.id_formation
            LEFT JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
            WHERE f.titre LIKE :formation 
              AND si.annee_scolaire = :annee
              AND i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ')
            GROUP BY etu.code_nip, etu.nom, etu.prenom, i.decision_jury
            HAVING nb_competences_validees >= :valeur
            ORDER BY nb_competences_validees DESC, etu.nom ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':formation', '%' . $formation . '%', PDO::PARAM_STR);
        $stmt->bindValue(':annee', $annee, PDO::PARAM_STR);
        $stmt->bindValue(':valeur', $valeur, PDO::PARAM_INT);
        $stmt->execute();
        
        $resultat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Regle inconnue.']);
        exit;
    }

    echo json_encode([
        'total' => count($resultat),
        'etudiants' => $resultat
    ]);

} catch (PDOException $e) {
    error_log("Erreur API regles : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'extraction des donnees.']);
}
