<?php
// Fichier: consultation_data.php
// Ce script récupère les données agrégées pour la visualisation Sankey.

include 'index.php'; // Connexion à la BDD

// Exemple : Récupérer le nombre d'étudiants pour chaque décision de jury
// (Ceci sera le Bilan des Compétences de jury affiché sous le Sankey)

$annee_choisie = 2024; // Exemple pour la cohorte 2024
$formation_code = 'INFO'; // Exemple pour le BUT INFO

try {
    $stmt = $pdo->prepare("
        SELECT 
            decision_code,
            COUNT(etud_hash) AS total_etudiants
        FROM 
            Parcours
        WHERE 
            annee_scolaire_debut = :annee AND libelle_formsemestre LIKE :formation_code_pattern
        GROUP BY 
            decision_code
    ");

    $stmt->execute([
        ':annee' => $annee_choisie,
        ':formation_code_pattern' => '%BUT1 ' . $formation_code . '%' // Exemple de filtre
    ]);

    $resultats_bilan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Résultats du bilan pour BUT1 $formation_code ($annee_choisie):\n";
    print_r($resultats_bilan);

    // Ensuite, le code insérera ces données dans un tableau HTML ou un format JSON pour le graphique Sankey.

} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}
?>
