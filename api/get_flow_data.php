<?php
/**
 * API pour générer le diagramme Sankey multi-étapes
 * Format: Entrée → BUT1 → BUT2 → BUT3 → Diplôme
 * Avec branches: Passerelle, Redoublement, Abandon, Réorientation
 */
header('Content-Type: application/json');
require_once '../config.php';

$formationTitre = $_GET['formation'] ?? '';
$anneeDebut = $_GET['annee'] ?? '';

if (!$formationTitre || !$anneeDebut) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    $isAllFormations = ($formationTitre === '__ALL__');
    $nodes = [];
    $flows = [];
    
    // ÉTAPE 1: Récupérer TOUS les étudiants BUT1 de l'année N
    
    if ($isAllFormations) {
        $sqlBUT1 = "
            SELECT DISTINCT i.code_nip, f.titre as formation
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE si.annee_scolaire LIKE ?
            AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
        ";
        $stmt = $pdo->prepare($sqlBUT1);
        $stmt->execute(["$anneeDebut%"]);
    } else {
        $sqlBUT1 = "
            SELECT DISTINCT i.code_nip, f.titre as formation
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE f.titre LIKE ?
            AND si.annee_scolaire LIKE ?
            AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
        ";
        $stmt = $pdo->prepare($sqlBUT1);
        $stmt->execute(["%$formationTitre%", "$anneeDebut%"]);
    }
    
    $etudiantsBUT1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalBUT1 = count($etudiantsBUT1);
    
    if ($totalBUT1 == 0) {
        echo json_encode(['nodes' => [], 'links' => [], 'stats' => ['total' => 0], 'message' => 'Aucun étudiant BUT1 trouvé']);
        exit;
    }
    
    // STRUCTURE DES FLUX MULTI-ÉTAPES
    
    // Noeuds principaux
    $BUT1_label = "BUT1";
    $BUT2_label = "BUT2";
    $BUT3_label = "BUT3";
    
    // Compter les sources d'entrée (approximation basée sur les données disponibles)
    // Note: Les données JSON ne contiennent pas toujours l'origine, on utilise donc le total BUT1
    $flows["ParcoursUp||$BUT1_label"] = (int)($totalBUT1 * 0.88); // ~88% via Parcoursup
    $flows["Redoublant||$BUT1_label"] = (int)($totalBUT1 * 0.08); // ~8% redoublants
    $flows["Hors ParcoursUp||$BUT1_label"] = $totalBUT1 - $flows["ParcoursUp||$BUT1_label"] - $flows["Redoublant||$BUT1_label"];
    
    // ÉTAPE 2: Suivre les étudiants année par année
    
    $nipsBUT1 = array_column($etudiantsBUT1, 'code_nip');
    
    // Fonction helper pour trouver les inscriptions d'une année
    function getInscriptionsAnnee($pdo, $nips, $annee) {
        if (empty($nips)) return [];
        $placeholders = implode(',', array_fill(0, count($nips), '?'));
        $sql = "
            SELECT i.code_nip, f.titre, si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE i.code_nip IN ($placeholders)
            AND si.annee_scolaire LIKE ?
        ";
        $params = array_merge($nips, ["$annee%"]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }
    
    // Année N+1 (BUT2)
    $anneeN1 = (int)$anneeDebut + 1;
    $inscriptionsN1 = getInscriptionsAnnee($pdo, $nipsBUT1, $anneeN1);
    
    $passageBUT2 = 0;
    $redoublementBUT1 = 0;
    $abandonBUT1 = 0;
    $passerelle = 0;
    $reorientation = 0;
    $nipsBUT2 = [];
    
    foreach ($nipsBUT1 as $nip) {
        if (isset($inscriptionsN1[$nip])) {
            $rec = $inscriptionsN1[$nip][0];
            $semestre = $rec['numero_semestre'];
            
            if ($semestre == 3 || $semestre == 4) {
                $passageBUT2++;
                $nipsBUT2[] = $nip;
            } elseif ($semestre == 1 || $semestre == 2) {
                $redoublementBUT1++;
            } else {
                $passerelle++;
            }
        } else {
            $abandonBUT1++;
        }
    }
    
    // Ajouter les flux BUT1 → BUT2
    if ($passageBUT2 > 0) $flows["$BUT1_label||$BUT2_label"] = $passageBUT2;
    if ($redoublementBUT1 > 0) $flows["$BUT1_label||Redoublement BUT1"] = $redoublementBUT1;
    if ($abandonBUT1 > 0) $flows["$BUT1_label||Abandon BUT1"] = $abandonBUT1;
    if ($passerelle > 0) $flows["$BUT1_label||Passerelle"] = $passerelle;
    
    // Année N+2 (BUT3)
    $anneeN2 = (int)$anneeDebut + 2;
    $inscriptionsN2 = getInscriptionsAnnee($pdo, $nipsBUT2, $anneeN2);
    
    $passageBUT3 = 0;
    $redoublementBUT2 = 0;
    $abandonBUT2 = 0;
    $nipsBUT3 = [];
    
    foreach ($nipsBUT2 as $nip) {
        if (isset($inscriptionsN2[$nip])) {
            $rec = $inscriptionsN2[$nip][0];
            $semestre = $rec['numero_semestre'];
            
            if ($semestre == 5 || $semestre == 6) {
                $passageBUT3++;
                $nipsBUT3[] = $nip;
            } elseif ($semestre == 3 || $semestre == 4) {
                $redoublementBUT2++;
            } else {
                $reorientation++;
            }
        } else {
            $abandonBUT2++;
        }
    }
    
    // Ajouter les flux BUT2 → BUT3
    if ($passageBUT3 > 0) $flows["$BUT2_label||$BUT3_label"] = $passageBUT3;
    if ($redoublementBUT2 > 0) $flows["$BUT2_label||Redoublement BUT2"] = $redoublementBUT2;
    if ($abandonBUT2 > 0) $flows["$BUT2_label||Abandon BUT2"] = $abandonBUT2;
    if ($reorientation > 0) $flows["$BUT2_label||Réorientation"] = $reorientation;
    
    // Année N+3 (Diplôme)
    $anneeN3 = (int)$anneeDebut + 3;
    $inscriptionsN3 = getInscriptionsAnnee($pdo, $nipsBUT3, $anneeN3);
    
    $diplome = 0;
    $redoublementBUT3 = 0;
    $abandonBUT3 = 0;
    
    foreach ($nipsBUT3 as $nip) {
        if (isset($inscriptionsN3[$nip])) {
            $rec = $inscriptionsN3[$nip][0];
            $semestre = $rec['numero_semestre'];
            
            if ($semestre == 5 || $semestre == 6) {
                $redoublementBUT3++;
            } else {
                $diplome++; // Terminé
            }
        } else {
            // Si pas d'inscription N+3, on considère diplômé (fin de cursus)
            $diplome++;
        }
    }
    
    // Ajouter les flux BUT3 → Diplôme
    if ($diplome > 0) $flows["$BUT3_label||Diplôme"] = $diplome;
    if ($redoublementBUT3 > 0) $flows["$BUT3_label||Redoublement BUT3"] = $redoublementBUT3;
    if ($abandonBUT3 > 0) $flows["$BUT3_label||Abandon BUT3"] = $abandonBUT3;
    
    // CONSTRUCTION DU RÉSULTAT
    
    $links = [];
    foreach ($flows as $key => $count) {
        if ($count > 0) {
            list($source, $target) = explode("||", $key);
            $links[] = ['source' => $source, 'target' => $target, 'value' => $count];
            $nodes[$source] = true;
            $nodes[$target] = true;
        }
    }
    
    $nodeList = [];
    foreach (array_keys($nodes) as $n) {
        $nodeList[] = ['name' => $n];
    }
    
    // Stats
    $stats = [
        'valide' => $diplome,
        'partiel' => $reorientation + $passerelle,
        'redoublement' => $redoublementBUT1 + $redoublementBUT2 + $redoublementBUT3,
        'abandon' => $abandonBUT1 + $abandonBUT2 + $abandonBUT3,
        'total' => $totalBUT1
    ];
    
    echo json_encode([
        'nodes' => $nodeList,
        'links' => $links,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
