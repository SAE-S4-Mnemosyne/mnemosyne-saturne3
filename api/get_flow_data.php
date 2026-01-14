<?php
/**
 * API pour generer le diagramme Sankey multi-etapes
 * Format conforme a l'exemple du professeur
 * 
 * Structure du diagramme:
 * - Entrees (gauche): Sources des etudiants BUT1
 * - Centre: BUT1 -> BUT2 -> BUT3 avec branches
 * - Sorties (droite): Diplome, Abandons, Reorientations
 */

require_once '../config.php';

$formationTitre = $_GET['formation'] ?? '';
$anneeDebut = $_GET['annee'] ?? '';

if (!$formationTitre || !$anneeDebut) {
    echo json_encode(['error' => 'Parametres manquants']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $isAllFormations = ($formationTitre === 'all' || $formationTitre === 'Toutes les formations');
    
    // Labels pour le Sankey
    $BUT1_label = "BUT1";
    $BUT2_label = "BUT2";
    $BUT3_label = "BUT3";
    
    // =========== RECUPERATION BUT1 ===========
    $annee1 = $anneeDebut;
    
    if ($isAllFormations) {
        $sqlBUT1 = "
            SELECT DISTINCT 
                i.code_nip, 
                f.titre as formation,
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE si.annee_scolaire = ?
            AND si.numero_semestre IN (1, 2)
        ";
        $stmt = $pdo->prepare($sqlBUT1);
        $stmt->execute([$annee1]);
    } else {
        $sqlBUT1 = "
            SELECT DISTINCT 
                i.code_nip, 
                f.titre as formation,
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE f.titre LIKE ?
            AND si.annee_scolaire = ?
            AND si.numero_semestre IN (1, 2)
        ";
        $stmt = $pdo->prepare($sqlBUT1);
        $stmt->execute(["%$formationTitre%", $annee1]);
    }
    
    $etudiantsBUT1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalBUT1 = count($etudiantsBUT1);
    
    if ($totalBUT1 == 0) {
        echo json_encode([
            'nodes' => [], 
            'links' => [], 
            'stats' => ['total' => 0], 
            'message' => 'Aucun etudiant BUT1 trouve pour cette annee'
        ]);
        exit;
    }
    
    // Compteurs BUT1
    $passageBUT2 = 0;
    $redoublementBUT1 = 0;
    $abandonBUT1 = 0;
    $nipsBUT2 = [];
    
    // Analyser les decisions de jury BUT1
    foreach ($etudiantsBUT1 as $etu) {
        $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
        $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
        
        // Mapping des decisions ScoDoc
        if (in_array($decision, ['ADM', 'PASD', 'PAS1', 'ADSUP', 'ADJ', 'ADJR', 'CMP'])) {
            // Passage en annee superieure
            $passageBUT2++;
            $nipsBUT2[] = $etu['code_nip'];
        } elseif (in_array($decision, ['RED', 'REDOUBLE', 'ATT', 'RAT'])) {
            // Redoublement
            $redoublementBUT1++;
        } elseif (in_array($decision, ['NAR', 'DEF', 'DEM', 'EXCLU', 'ABL', 'ABS'])) {
            // Abandon / Sortie
            $abandonBUT1++;
        } elseif ($etat === 'D') {
            // Demission
            $abandonBUT1++;
        } else {
            // Decision inconnue ou vide - on suppose passage
            $passageBUT2++;
            $nipsBUT2[] = $etu['code_nip'];
        }
    }
    
    // =========== RECUPERATION BUT2 ===========
    $annee2 = (int)$anneeDebut + 1;
    $passageBUT3 = 0;
    $redoublementBUT2 = 0;
    $abandonBUT2 = 0;
    $reorientationBUT2 = 0;
    $nipsBUT3 = [];
    
    if (!empty($nipsBUT2)) {
        $placeholders = implode(',', array_fill(0, count($nipsBUT2), '?'));
        $sqlBUT2 = "
            SELECT DISTINCT 
                i.code_nip, 
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE i.code_nip IN ($placeholders)
            AND si.annee_scolaire = ?
            AND si.numero_semestre IN (3, 4)
        ";
        
        $params = array_merge($nipsBUT2, [$annee2]);
        $stmt = $pdo->prepare($sqlBUT2);
        $stmt->execute($params);
        $etudiantsBUT2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $but2ByNip = [];
        foreach ($etudiantsBUT2 as $etu) {
            $but2ByNip[$etu['code_nip']] = $etu;
        }
        
        // Analyser chaque etudiant suppose passer en BUT2
        foreach ($nipsBUT2 as $nip) {
            if (isset($but2ByNip[$nip])) {
                $etu = $but2ByNip[$nip];
                $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
                
                if (in_array($decision, ['ADM', 'PASD', 'PAS1', 'ADSUP', 'ADJ', 'ADJR', 'CMP'])) {
                    $passageBUT3++;
                    $nipsBUT3[] = $nip;
                } elseif (in_array($decision, ['RED', 'REDOUBLE', 'ATT', 'RAT'])) {
                    $redoublementBUT2++;
                } elseif (in_array($decision, ['NAR', 'DEF', 'DEM', 'EXCLU'])) {
                    $abandonBUT2++;
                } else {
                    // Decision vide = passage presume
                    $passageBUT3++;
                    $nipsBUT3[] = $nip;
                }
            } else {
                // Pas trouve en BUT2 = reorientation ou suivi non disponible
                $reorientationBUT2++;
            }
        }
    }
    
    // =========== RECUPERATION BUT3 ===========
    $annee3 = (int)$anneeDebut + 2;
    $diplome = 0;
    $redoublementBUT3 = 0;
    $abandonBUT3 = 0;
    
    if (!empty($nipsBUT3)) {
        $placeholders = implode(',', array_fill(0, count($nipsBUT3), '?'));
        $sqlBUT3 = "
            SELECT DISTINCT 
                i.code_nip, 
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE i.code_nip IN ($placeholders)
            AND si.annee_scolaire = ?
            AND si.numero_semestre IN (5, 6)
        ";
        
        $params = array_merge($nipsBUT3, [$annee3]);
        $stmt = $pdo->prepare($sqlBUT3);
        $stmt->execute($params);
        $etudiantsBUT3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $but3ByNip = [];
        foreach ($etudiantsBUT3 as $etu) {
            $but3ByNip[$etu['code_nip']] = $etu;
        }
        
        foreach ($nipsBUT3 as $nip) {
            if (isset($but3ByNip[$nip])) {
                $etu = $but3ByNip[$nip];
                $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
                
                if (in_array($decision, ['RED', 'REDOUBLE', 'ATT', 'RAT'])) {
                    $redoublementBUT3++;
                } elseif (in_array($decision, ['NAR', 'DEF', 'DEM', 'EXCLU'])) {
                    $abandonBUT3++;
                } else {
                    // ADM ou autre = Diplome
                    $diplome++;
                }
            } else {
                // Pas de donnees BUT3 - presume diplome
                $diplome++;
            }
        }
    }
    
    // =========== CONSTRUCTION DU SANKEY ===========
    $flows = [];
    
    // Entrees vers BUT1 (on ne peut pas distinguer ParcoursUp/eCandidat)
    // On affiche juste "Entrants" avec le total
    $entrants = $totalBUT1;
    $flows["Entrants||$BUT1_label"] = $entrants;
    
    // BUT1 vers destinations
    if ($passageBUT2 > 0) $flows["$BUT1_label||$BUT2_label"] = $passageBUT2;
    if ($redoublementBUT1 > 0) $flows["$BUT1_label||Redoublement BUT1"] = $redoublementBUT1;
    if ($abandonBUT1 > 0) $flows["$BUT1_label||Abandon BUT1"] = $abandonBUT1;
    
    // BUT2 vers destinations
    if ($passageBUT3 > 0) $flows["$BUT2_label||$BUT3_label"] = $passageBUT3;
    if ($redoublementBUT2 > 0) $flows["$BUT2_label||Redoublement BUT2"] = $redoublementBUT2;
    if ($abandonBUT2 > 0) $flows["$BUT2_label||Abandon BUT2"] = $abandonBUT2;
    if ($reorientationBUT2 > 0) $flows["$BUT2_label||Reorientation"] = $reorientationBUT2;
    
    // BUT3 vers destinations
    if ($diplome > 0) $flows["$BUT3_label||Diplome"] = $diplome;
    if ($redoublementBUT3 > 0) $flows["$BUT3_label||Redoublement BUT3"] = $redoublementBUT3;
    if ($abandonBUT3 > 0) $flows["$BUT3_label||Abandon BUT3"] = $abandonBUT3;
    
    // Construire les noeuds et liens pour Google Charts
    $nodes = [];
    $links = [];
    
    foreach ($flows as $key => $value) {
        if ($value > 0) {
            list($from, $to) = explode('||', $key);
            $nodes[$from] = true;
            $nodes[$to] = true;
            $links[] = ['source' => $from, 'target' => $to, 'value' => $value];
        }
    }
    
    $nodeList = array_keys($nodes);
    
    // Statistiques
    $totalRedoublement = $redoublementBUT1 + $redoublementBUT2 + $redoublementBUT3;
    $totalAbandon = $abandonBUT1 + $abandonBUT2 + $abandonBUT3 + $reorientationBUT2;
    
    $stats = [
        'entrants' => $entrants,
        'but1' => $totalBUT1,
        'but2' => $passageBUT2,
        'but3' => $passageBUT3,
        'diplome' => $diplome,
        'redoublement' => $totalRedoublement,
        'abandon' => $totalAbandon,
        'total' => $totalBUT1,
        'details' => [
            'redoublement_but1' => $redoublementBUT1,
            'abandon_but1' => $abandonBUT1,
            'redoublement_but2' => $redoublementBUT2,
            'abandon_but2' => $abandonBUT2,
            'reorientation' => $reorientationBUT2,
            'redoublement_but3' => $redoublementBUT3,
            'abandon_but3' => $abandonBUT3
        ]
    ];
    
    echo json_encode([
        'nodes' => $nodeList,
        'links' => $links,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
