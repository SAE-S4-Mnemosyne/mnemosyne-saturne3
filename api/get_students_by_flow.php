<?php
/**
 * API pour récupérer la liste des étudiants d'un flux spécifique
 * Conformité Cahier des Charges : Valeurs cliquables
 * 
 * Note: decision_jury contient les codes (ADM, RED, NAR, etc.)
 *       decision_annee contient le niveau (1, 2, 3) - NE PAS UTILISER
 */
header('Content-Type: application/json');
require_once '../config.php';

$formationTitre = $_GET['formation'] ?? '';
$anneeDebut = $_GET['annee'] ?? '';
$source = $_GET['source'] ?? '';
$target = $_GET['target'] ?? '';

if (!$formationTitre || !$anneeDebut || !$source) {
    echo json_encode(['error' => 'Paramètres manquants (formation, annee, source requis)']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $isAllFormations = ($formationTitre === '__ALL__');
    $students = [];

    // Identifier le niveau BUT demandé
    $niveau = 1;
    if (strpos($source, 'BUT2') !== false) $niveau = 2;
    elseif (strpos($source, 'BUT3') !== false || strpos($source, 'Diplôm') !== false) $niveau = 3;
    
    // Calculer l'année et les semestres correspondants
    $anneeNiveau = (int)$anneeDebut + ($niveau - 1);
    $semMin = ($niveau - 1) * 2 + 1;
    $semMax = $niveau * 2;

    // Requête - decision_jury contient les vrais codes (ADM, RED, NAR, etc.)
    $sql = "
        SELECT DISTINCT 
            i.code_nip, 
            e.etudid_scodoc,
            i.decision_jury,
            i.etat_inscription,
            f.titre as formation,
            si.annee_scolaire,
            si.numero_semestre
        FROM inscription i
        JOIN etudiant e ON i.code_nip = e.code_nip
        JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN formation f ON si.id_formation = f.id_formation
        WHERE si.annee_scolaire LIKE :annee
        AND si.numero_semestre >= :semMin
        AND si.numero_semestre <= :semMax
    ";
    
    $params = [
        ':annee' => "$anneeNiveau%",
        ':semMin' => $semMin,
        ':semMax' => $semMax
    ];

    if (!$isAllFormations) {
        $sql .= " AND f.titre LIKE :formation ";
        $params[':formation'] = "%$formationTitre%";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtrage selon Target
    foreach ($candidates as $etu) {
        $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
        $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
        $isMatch = false;

        // Catégorisation des décisions - IDENTIQUE à get_flow_data.php
        $isAbandon = ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM');
        $isRedoublement = ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ');
        $isPassage = ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ');
        
        // Si pas de décision claire et état I, c'est un passage (comme dans get_flow_data.php)
        if (!$isAbandon && !$isRedoublement && !$isPassage) {
            if ($etat === 'I' || empty($decision)) {
                $isPassage = true;
            } else {
                $isAbandon = true;
            }
        }

        // Si target est vide, retourner tous les étudiants du niveau
        if (empty($target)) {
            $isMatch = true;
        } else {
            if (strpos($target, 'Redoublem') !== false && $isRedoublement) {
                $isMatch = true;
            } elseif ((strpos($target, 'Sortie') !== false || strpos($target, 'Abandon') !== false) && $isAbandon) {
                $isMatch = true;
            } elseif (strpos($target, 'Diplôm') !== false && $isPassage) {
                $isMatch = true;
            } elseif (strpos($target, 'BUT') !== false && $isPassage) {
                $isMatch = true;
            }
        }

        if ($isMatch) {
            $sem = (int)$etu['numero_semestre'];
            
            // Ordre de priorité pour le tri (meilleur au pire)
            $ordreDecision = 99;
            if ($decision === 'ADM') $ordreDecision = 1;
            elseif ($decision === 'ADSUP') $ordreDecision = 2;
            elseif ($decision === 'PASD') $ordreDecision = 3;
            elseif ($decision === 'ADJ') $ordreDecision = 4;
            elseif ($decision === 'CMP') $ordreDecision = 5;
            elseif (empty($decision)) $ordreDecision = 10;
            elseif ($decision === 'ATJ') $ordreDecision = 20;
            elseif ($decision === 'AJ') $ordreDecision = 21;
            elseif ($decision === 'RED') $ordreDecision = 22;
            elseif ($decision === 'NAR') $ordreDecision = 30;
            elseif ($decision === 'DEM') $ordreDecision = 31;
            elseif ($decision === 'DEF') $ordreDecision = 32;
            
            $students[] = [
                'nip' => $etu['code_nip'],
                'scodoc_id' => $etu['etudid_scodoc'] ?? null,
                'decision' => $decision ?: 'En cours',
                'formation' => $etu['formation'],
                'annee' => $etu['annee_scolaire'] ?? $anneeNiveau,
                'semestre' => 'S' . $sem,
                'ordre' => $ordreDecision
            ];
        }
    }

    // Tri par ordre de décision (meilleur au pire)
    usort($students, function($a, $b) {
        return $a['ordre'] - $b['ordre'];
    });

    echo json_encode([
        'students' => $students,
        'debug' => [
            'niveau' => "BUT$niveau (S$semMin-S$semMax)",
            'annee' => $anneeNiveau,
            'nb_candidats' => count($candidates),
            'nb_filtres' => count($students)
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
