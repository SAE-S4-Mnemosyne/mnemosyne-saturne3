<?php
header('Content-Type: application/json');
require_once '../config.php';

$formationTitre = $_GET['formation'] ?? '';
$anneeDebut = $_GET['annee'] ?? ''; // ex: 2022 (pour 2022-2023)

if (!$formationTitre || !$anneeDebut) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    // --- Algorithme : Suivi de Cohorte ---
    
    // 1. Identifier les étudiants inscrits en BUT1 dans la formation cible l'année donnée
    // On cherche les étudiants inscrits dans un Semestre 1 ou 2 de cette formation cette année là.
    
    // Année N (Source)
    $sqlSource = "
        SELECT DISTINCT i.code_nip
        FROM INSCRIPTION i
        JOIN SEMESTRE_INSTANCE si ON i.id_formsemestre = si.id_formsemestre
        JOIN FORMATION f ON si.id_formation = f.id_formation
        WHERE f.titre = ? 
        AND si.annee_scolaire LIKE ? 
        AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
    ";
    
    // On prépare le pattern d'année (ex: '2022%')
    $stmt = $pdo->prepare($sqlSource);
    $stmt->execute([$formationTitre, "$anneeDebut%"]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $nodes = [];
    $links = [];
    $flows = []; // "Source -> Target" => count

    // Ajout du noeud racine
    $rootNode = "BUT1 $formationTitre ($anneeDebut)";
    $nodes[$rootNode] = true;
    
    if (empty($etudiants)) {
         echo json_encode(['nodes' => [], 'links' => [], 'message' => 'Aucun étudiant trouvé']);
         exit;
    }

    // 2. Pour chaque étudiant, chercher son statut l'année SUIVANTE (N+1)
    $anneeSuivanteInt = (int)$anneeDebut + 1;
    
    $placeholders = implode(',', array_fill(0, count($etudiants), '?'));
    
    $sqlTarget = "
        SELECT i.code_nip, f.titre, si.numero_semestre, i.decision_annee
        FROM INSCRIPTION i
        JOIN SEMESTRE_INSTANCE si ON i.id_formsemestre = si.id_formsemestre
        JOIN FORMATION f ON si.id_formation = f.id_formation
        WHERE i.code_nip IN ($placeholders)
        AND si.annee_scolaire LIKE ?
    ";
    
    $params = array_merge($etudiants, ["$anneeSuivanteInt%"]);
    $stmt = $pdo->prepare($sqlTarget);
    $stmt->execute($params);
    $resultatsNplus1 = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC); // Group by NIP
    
    
    foreach ($etudiants as $nip) {
        $targetNode = "Sortie / Inconnu"; // Par défaut
        
        if (isset($resultatsNplus1[$nip])) {
            // L'étudiant a une inscription l'année suivante
            // On prend la première (ou la plus significative si plusieurs)
            $rec = $resultatsNplus1[$nip][0];
            $titre = $rec['titre'];
            $semestre = $rec['numero_semestre'];
            
            if ($titre == $formationTitre) {
                // Même formation
                if ($semestre == 3 || $semestre == 4) {
                     $targetNode = "Passage BUT2";
                } elseif ($semestre == 1 || $semestre == 2) {
                     $targetNode = "Redoublement BUT1";
                } else {
                     $targetNode = "BUT $titre (Autre)";
                }
            } else {
                // Autre formation -> Réorientation
                $targetNode = "Réorientation : $titre";
            }
        }
        
        // Comptage des flux
        $key = $rootNode . "||" . $targetNode;
        if (!isset($flows[$key])) $flows[$key] = 0;
        $flows[$key]++;
        
        $nodes[$targetNode] = true;
    }

    // Formatage pour Sankey (D3/Google Charts)
    foreach ($flows as $key => $count) {
        list($source, $target) = explode("||", $key);
        $links[] = ['source' => $source, 'target' => $target, 'value' => $count];
    }
    
    // Transformation des array keys en liste simple pour nodes
    $nodeList = [];
    foreach (array_keys($nodes) as $n) {
        $nodeList[] = ['name' => $n];
    }

    echo json_encode([
        'nodes' => $nodeList,
        'links' => $links
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
