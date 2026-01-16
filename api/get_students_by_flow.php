<?php
/**
 * API pour récupérer la liste des étudiants d'un flux spécifique
 * 
 * VERSION CORRIGÉE :
 * - Filtrage strict des diplômés (uniquement ADM)
 * - Normalisation des formations (Alternance = FI)
 * - Cohérence avec get_flow_data.php
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

/**
 * Fonction pour normaliser le nom de formation
 */
function normaliseFormation($titre) {
    $patterns = [
        '/BUT\s*(GEA|Gestion)/i' => 'GEA',
        '/BUT\s*(INFO|Informatique)/i' => 'INFO',
        '/BUT\s*(SD|Science.*donn|STID)/i' => 'SD',
        '/BUT\s*(R.*T|R[eé]seaux)/i' => 'RT',
        '/BUT\s*(GEII|G[eé]nie.*[eé]lectrique)/i' => 'GEII',
        '/BUT\s*(CJ|Carri[eè]res.*Juridiques)/i' => 'CJ',
    ];
    
    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $titre)) {
            return $type;
        }
    }
    return $titre;
}

function matchFormation($titreDB, $formationRecherche) {
    if ($formationRecherche === '__ALL__') return true;
    
    $typeDB = normaliseFormation($titreDB);
    $typeRecherche = normaliseFormation($formationRecherche);
    
    return $typeDB === $typeRecherche || 
           stripos($titreDB, $formationRecherche) !== false ||
           stripos($formationRecherche, $typeDB) !== false;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $isAllFormations = ($formationTitre === '__ALL__');
    $students = [];
    
    // Calculer les années
    $anneeBUT1 = (int)$anneeDebut;
    $anneeBUT2 = $anneeBUT1 + 1;
    $anneeBUT3 = $anneeBUT1 + 2;
    
    // Déterminer le niveau et l'année concernés
    $niveau = 1;
    $anneeRecherche = $anneeBUT1;
    
    if (strpos($source, 'BUT3') !== false || strpos($target, 'BUT3') !== false || 
        strpos($target, 'Diplômé') !== false || strpos($target, 'En cours BUT3') !== false ||
        strpos($target, 'Abandon BUT3') !== false || strpos($target, 'Redoublement BUT3') !== false) {
        $niveau = 3;
        $anneeRecherche = $anneeBUT3;
    } elseif (strpos($source, 'BUT2') !== false || strpos($target, 'BUT2') !== false ||
              strpos($source, 'Passerelle') !== false ||
              strpos($target, 'En cours BUT2') !== false || strpos($target, 'Abandon BUT2') !== false ||
              strpos($target, 'Redoublement BUT2') !== false || strpos($target, 'Réorientation') !== false) {
        $niveau = 2;
        $anneeRecherche = $anneeBUT2;
    }
    
    $semMin = ($niveau - 1) * 2 + 1;
    $semMax = $niveau * 2;
    
    // Requête de base
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
        ORDER BY si.numero_semestre DESC
    ";
    
    $params = [
        ':annee' => "$anneeRecherche%",
        ':semMin' => $semMin,
        ':semMax' => $semMax
    ];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrer par formation et indexer par nip
    $candidatesByNip = [];
    foreach ($candidates as $etu) {
        if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
            $nip = $etu['code_nip'];
            $sem = (int)$etu['numero_semestre'];
            if (!isset($candidatesByNip[$nip]) || $sem > $candidatesByNip[$nip]['numero_semestre']) {
                $candidatesByNip[$nip] = $etu;
            }
        }
    }
    
    // Pour les entrées spéciales (Redoublant, Nouveaux inscrits, Passerelle)
    if ($source === 'Redoublant' || $source === 'Nouveaux inscrits' || $source === 'Passerelle') {
        // Récupérer les étudiants BUT1 de l'année N-1 pour identifier les redoublants
        $anneeN1 = $anneeBUT1 - 1;
        $sqlRedoublants = "
            SELECT DISTINCT i.code_nip
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            WHERE si.annee_scolaire LIKE ?
            AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
        ";
        $stmtRed = $pdo->prepare($sqlRedoublants);
        $stmtRed->execute(["$anneeN1%"]);
        $redoublantsBUT1 = array_flip($stmtRed->fetchAll(PDO::FETCH_COLUMN));
        
        if ($source === 'Redoublant') {
            foreach ($candidatesByNip as $nip => $etu) {
                if (isset($redoublantsBUT1[$nip])) {
                    $students[] = formatStudent($etu);
                }
            }
        } elseif ($source === 'Nouveaux inscrits') {
            foreach ($candidatesByNip as $nip => $etu) {
                if (!isset($redoublantsBUT1[$nip])) {
                    $students[] = formatStudent($etu);
                }
            }
        } elseif ($source === 'Passerelle') {
            // Récupérer les étudiants BUT1 de cette cohorte
            $sqlBUT1 = "
                SELECT DISTINCT i.code_nip
                FROM inscription i
                JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
                WHERE si.annee_scolaire LIKE ?
                AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
            ";
            $stmtBUT1 = $pdo->prepare($sqlBUT1);
            $stmtBUT1->execute(["$anneeBUT1%"]);
            $butUnNips = array_flip($stmtBUT1->fetchAll(PDO::FETCH_COLUMN));
            
            // Récupérer étudiants BUT2 (passerelles = ceux qui ne sont pas dans BUT1)
            $sqlBUT2 = "
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
                WHERE si.annee_scolaire LIKE ?
                AND (si.numero_semestre = 3 OR si.numero_semestre = 4)
            ";
            $stmtBUT2 = $pdo->prepare($sqlBUT2);
            $stmtBUT2->execute(["$anneeBUT2%"]);
            $etudiantsBUT2 = $stmtBUT2->fetchAll(PDO::FETCH_ASSOC);
            
            $but2ByNip = [];
            foreach ($etudiantsBUT2 as $etu) {
                if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
                    $nip = $etu['code_nip'];
                    $sem = (int)$etu['numero_semestre'];
                    if (!isset($but2ByNip[$nip]) || $sem > $but2ByNip[$nip]['numero_semestre']) {
                        $but2ByNip[$nip] = $etu;
                    }
                }
            }
            
            foreach ($but2ByNip as $nip => $etu) {
                if (!isset($butUnNips[$nip])) {
                    $students[] = formatStudent($etu);
                }
            }
        }
    } else {
        // Filtrage selon Target
        foreach ($candidatesByNip as $nip => $etu) {
            $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
            $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
            $isMatch = false;

            // Catégorisation stricte
            $isAbandon = ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM');
            $isRedoublement = ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ');
            // ADM uniquement pour diplômés et passages
            $isAdmis = ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'CMP' || $decision === 'ADJ');
            $isPassage = ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ');
            $isEnCours = !$isAbandon && !$isRedoublement && !$isPassage && ($etat === 'I' || empty($decision));

            if (empty($target)) {
                // Clic sur noeud : tous les étudiants du niveau
                $isMatch = true;
            } else {
                // Correspondance stricte selon le target
                if (strpos($target, 'Redoublement') !== false && $isRedoublement) {
                    $isMatch = true;
                } elseif (strpos($target, 'Abandon') !== false && $isAbandon) {
                    $isMatch = true;
                } elseif (strpos($target, 'Diplômé') !== false && $isAdmis) {
                    // UNIQUEMENT les admis pour les diplômés
                    $isMatch = true;
                } elseif (strpos($target, 'En cours') !== false && $isEnCours) {
                    $isMatch = true;
                } elseif (strpos($target, 'BUT') !== false && $isPassage) {
                    // Passage vers niveau suivant = admis
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $students[] = formatStudent($etu);
            }
        }
    }

    // Tri par ordre de décision
    usort($students, function($a, $b) {
        return $a['ordre'] - $b['ordre'];
    });

    echo json_encode([
        'students' => $students,
        'debug' => [
            'niveau' => "BUT$niveau (S$semMin-S$semMax)",
            'annee_recherchee' => $anneeRecherche,
            'nb_candidats' => count($candidatesByNip),
            'nb_filtres' => count($students),
            'source' => $source,
            'target' => $target
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Formate un étudiant pour l'affichage
 */
function formatStudent($etu) {
    $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
    $sem = (int)$etu['numero_semestre'];
    
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
    
    return [
        'nip' => $etu['code_nip'],
        'scodoc_id' => $etu['etudid_scodoc'] ?? null,
        'decision' => $decision ?: 'En cours',
        'formation' => $etu['formation'],
        'annee' => $etu['annee_scolaire'] ?? '',
        'semestre' => 'S' . $sem,
        'ordre' => $ordreDecision
    ];
}
