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
require_once 'utilitaires.php'; // Inclusion des fonctions utilitaires partagées

// Récupérer les données POST (JSON)
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$filterNips = $input['nips'] ?? null;

$formationTitre = $input['formation'] ?? $_GET['formation'] ?? '';
$anneeDebut = $input['annee'] ?? $_GET['annee'] ?? '';
$source = $input['source'] ?? $_GET['source'] ?? '';
$target = $input['target'] ?? $_GET['target'] ?? '';
// Les filtres ne sont utilisés que si pas de liste de NIPs fournie (fallback)
$filterRegime = $input['regime'] ?? $_GET['regime'] ?? 'ALL';
$filterStatus = $input['status'] ?? $_GET['status'] ?? 'ALL';


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
    
    // Calculer les années
    $anneeBUT1 = (int)$anneeDebut;
    $anneeBUT2 = $anneeBUT1 + 1;
    $anneeBUT3 = $anneeBUT1 + 2;
    
    // Nettoyer les paramètres
    $source = rawurldecode($source);
    $target = rawurldecode($target);
    
    // Fonction utilitaire pour normaliser les chaines (retirer accents, majuscules)
    function sanitizeStr($str) {
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(
            ['à','á','â','ã','ä', 'ç', 'è','é','ê','ë', 'ì','í','î','ï', 'ñ', 'ò','ó','ô','õ','ö', 'ù','ú','û','ü', 'ý','ÿ', '°'],
            ['a','a','a','a','a', 'c', 'e','e','e','e', 'i','i','i','i', 'n', 'o','o','o','o','o', 'u','u','u','u', 'y','y', ''],
            $str
        );
        return $str;
    }

    // Séparer le filtrage par NIP du filtrage logique
    function isStudentSelected($etu, $filterNips, $regimeFilter, $statusFilter) {
        if ($filterNips !== null && is_array($filterNips)) {
            // Filtrage par liste de NIPs (prioritaire)
            // Conversion en string par sécurité
            return in_array((string)$etu['code_nip'], $filterNips);
        }
        // Sinon filtrage classique
        return shouldKeepStudent($etu, $regimeFilter, $statusFilter);
    }
    
    $s_clean = sanitizeStr($source);
    $t_clean = sanitizeStr($target);
    
    // Déterminer le niveau et l'année concernés
    $niveau = 1;
    $anneeRecherche = $anneeBUT1;
    
    // Détection robuste du niveau (via BUTx ou 1ère/2ème/3ème année)
    
    // NIVEAU 3
    // Mots clés: but3, 3eme, 3e, diplome (dans source OU target)
    if (strpos($s_clean, 'but3') !== false || strpos($t_clean, 'but3') !== false || 
        strpos($s_clean, '3eme') !== false || strpos($t_clean, '3eme') !== false ||
        strpos($s_clean, '3e') !== false || strpos($t_clean, '3e') !== false ||
        strpos($t_clean, 'diplome') !== false || strpos($s_clean, 'diplome') !== false) {
        
        $niveau = 3;
        $anneeRecherche = $anneeBUT3;
        
    // NIVEAU 2
    // Mots clés: but2, 2eme, 2e, passerelle
    } elseif (strpos($s_clean, 'but2') !== false || strpos($t_clean, 'but2') !== false ||
              strpos($s_clean, '2eme') !== false || strpos($t_clean, '2eme') !== false ||
              strpos($s_clean, '2e') !== false || strpos($t_clean, '2e') !== false ||
              strpos($s_clean, 'passerelle') !== false) {
              
        $niveau = 2;
        $anneeRecherche = $anneeBUT2;
    }
    
    
    $semMin = ($niveau - 1) * 2 + 1;
    $semMax = $niveau * 2;
    
    // NOTE : On filtre sur l'année concernée pour récupérer la liste.
    
    // Requête pour récupérer les étudiants du niveau concerné
    $sql = "
        SELECT DISTINCT 
            i.code_nip, 
            e.etudid_scodoc,
            i.decision_jury,
            i.etat_inscription,
            f.titre as formation,
            f.code_scodoc as code_formation,
            si.annee_scolaire,
            si.numero_semestre
        FROM Inscription i
        JOIN Etudiant e ON i.code_nip = e.code_nip
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN Formation f ON si.id_formation = f.id_formation
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
    
    // Filtrer par formation et indexer par nip (garder le semestre le plus élevé)
    $candidatesByNip = [];
    foreach ($candidates as $etu) {
        if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
            $nip = $etu['code_nip'];
            $sem = (int)$etu['numero_semestre'];
            
            if (!isset($candidatesByNip[$nip]) || $sem > $candidatesByNip[$nip]['numero_semestre']) {
                if (isStudentSelected($etu, $filterNips, $filterRegime, $filterStatus)) {
                    $candidatesByNip[$nip] = $etu;
                }
            }
        }
    }
    
    // --- CALCUL DU NIVEAU ACTUEL (Pour synchroniser logique avec get_flow_data) ---
    // Année actuelle pour déterminer si la promo est "en cours"
    $anneeActuelle = (int)date('Y');
    $moisActuel = (int)date('m');
    if ($moisActuel < 9) {
        $anneeActuelle--;
    }
    
    // Niveau théorique actuel de cette cohorte
    $niveauPromo = 4; // Par défaut : terminée
    if ($anneeBUT1 >= $anneeActuelle) {
        $niveauPromo = 1;
    } elseif ($anneeBUT2 >= $anneeActuelle) {
        $niveauPromo = 2;
    } elseif ($anneeBUT3 >= $anneeActuelle) {
        $niveauPromo = 3;
    }
    
    // Logique de filtrage spécifique selon le noeud cliqué
    if (stripos($source, 'Redoublant') !== false || stripos($source, 'Nouveaux inscrits') !== false || stripos($source, 'Passerelle') !== false) {
        // Logique simplifiée : on utilise la sélection standard
        foreach ($candidatesByNip as $nip => $etu) {
            $students[] = formatStudent($etu, $formationTitre);
        }
    } else {
        // Filtrage selon Target (Le clic sur la partie droite du diagramme)
        foreach ($candidatesByNip as $nip => $etu) {
            $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
            $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
            $isMatch = false;

            // Catégorisation stricte
            $isAbandon = ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM');
            $isRedoublement = ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ');
            $hasExplicitAdmis = ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'CMP' || $decision === 'ADJ');
            $isPassage = $hasExplicitAdmis || $decision === 'PASD';
            
            // Logique "En Cours" stricte
            $isEnCoursStrict = !$isAbandon && !$isRedoublement && ($etat === 'I' || empty($decision));

            // DÉTERMINATION DU STATUT "DIPLÔMÉ" (ou PASSAGE SUP) AVEC FALLBACK
            // Si la promo est terminée (niveau > niveauRecherche) et que l'étudiant n'a pas échoué,
            // on le considère comme validé par défaut (comme le fait le Sankey).
            $isAdmisImplicit = false;
            
            // Si on regarde BUT3 et que la promo est finie (niveauPromo > 3)
            if ($niveau == 3 && $niveauPromo > 3) {
                 if (!$isAbandon && !$isRedoublement) {
                     $isAdmisImplicit = true;
                 }
            }
            // Si on regarde BUT2 et que promo > 2
            elseif ($niveau == 2 && $niveauPromo > 2) {
                // Pour BUT2, "Passage" inclut implicitement ceux scolarisés en année sup
                if (!$isAbandon && !$isRedoublement) {
                     $isAdmisImplicit = true; // Considé comme ayant passé
                     $isPassage = true;
                 }
            }

            $isAdmisFinal = $hasExplicitAdmis || $isAdmisImplicit;
            
            // Ajustement de "En cours" : Si implicitement admis, il n'est plus "En cours"
            if ($isAdmisImplicit) {
                $isEnCoursStrict = false;
            }

            // --- FILTRAGE SELON TARGET/SOURCE ---

            $shouldFilter = !empty($target);
            if (empty($target)) {
                if (strpos($s_clean, 'diplome') !== false || 
                    strpos($s_clean, 'abandon') !== false || 
                    strpos($s_clean, 'redoublement') !== false) {
                    $shouldFilter = true;
                    $filterTerm = $s_clean; 
                } else {
                    $filterTerm = '';
                }
            } else {
                $filterTerm = $t_clean;
            }

            if ($filterNips) {
                // Si on a une liste explicite de NIPs (venant du Sankey), on fait confiance à 100%
                $isMatch = true;
                
                // Override statut si c'est le flux Diplômé
                if (strpos($filterTerm, 'diplome') !== false) {
                    $etu['decision_jury_override'] = 'Diplômé';
                }
            } elseif (!$shouldFilter) {
                // Clic sur un noeud "conteneur" (ex: BUT2) -> Tous les étudiants présents
                $isMatch = true;
            } else {
                // Correspondance stricte selon le terme de filtre (Target ou Source finale)
                
                // Cas 1 : Redoublements
                if (strpos($filterTerm, 'redoublement') !== false && $isRedoublement) {
                    $isMatch = true;
                } 
                // Cas 2 : Abandons
                elseif (strpos($filterTerm, 'abandon') !== false && $isAbandon) {
                    $isMatch = true;
                } 
                // Cas 3 : Diplômés (Spécifique BUT3)
                elseif (strpos($filterTerm, 'diplome') !== false) {
                    if ($isAdmisFinal) {
                        $isMatch = true;
                    }
                } 
                // Cas 4 : En cours
                elseif (strpos($filterTerm, 'en cours') !== false && $isEnCoursStrict) {
                    $isMatch = true;
                } 
                // Cas 5 : Passages
                elseif ((strpos($filterTerm, 'but') !== false || strpos($filterTerm, 'eme') !== false || strpos($filterTerm, '3e') !== false || strpos($filterTerm, '2e') !== false) 
                        && $isPassage) {
                    $isMatch = true;
                }
                // Cas 6 : Réorientation
                elseif (strpos($filterTerm, 'reorientation') !== false) {
                    if ($isAbandon) $isMatch = true; 
                }
            }


            if ($isMatch) {
                // IMPORTANT : Si la personne est diplômée, on force l'affichage 'Diplômé'
                if (strpos($filterTerm, 'diplome') !== false && $isAdmisFinal) {
                    $etu['decision_jury_override'] = 'Diplômé';
                }
                
                // Forcer l'affichage du semestre final correspondant au niveau pour TOUS les affichages
                // (Cohérence : vue annuelle => semestre de fin)
                if ($niveau == 3) $etu['numero_semestre'] = 6;
                elseif ($niveau == 2) $etu['numero_semestre'] = 4;
                elseif ($niveau == 1) $etu['numero_semestre'] = 2;

                $students[] = formatStudent($etu, $formationTitre);
            }
        }
    }

    // Tri par ordre de décision (Meilleurs en premier)
    usort($students, function($a, $b) {
        return $a['ordre'] - $b['ordre'];
    });

    echo json_encode(['students' => $students]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

    
/**
 * Formate un étudiant pour l'affichage
 */
function formatStudent($etu, $context = null) {
    // Utiliser l'override si défini (ex: pour afficher "Diplômé" au lieu de ADM)
    $decisionAffichee = $etu['decision_jury_override'] ?? strtoupper(trim($etu['decision_jury'] ?? ''));
    $decisionReelle = strtoupper(trim($etu['decision_jury'] ?? ''));
    
    $sem = (int)$etu['numero_semestre'];
    
    $ordreDecision = 99;
    if ($decisionReelle === 'ADM') $ordreDecision = 1;
    elseif ($decisionReelle === 'ADSUP') $ordreDecision = 2;
    elseif ($decisionReelle === 'PASD') $ordreDecision = 3;
    elseif ($decisionReelle === 'ADJ') $ordreDecision = 4;
    elseif ($decisionReelle === 'CMP') $ordreDecision = 5;
    elseif (empty($decisionReelle)) $ordreDecision = 10;
    elseif ($decisionReelle === 'ATJ') $ordreDecision = 20;
    elseif ($decisionReelle === 'AJ') $ordreDecision = 21;
    elseif ($decisionReelle === 'RED') $ordreDecision = 22;
    elseif ($decisionReelle === 'NAR') $ordreDecision = 30;
    elseif ($decisionReelle === 'DEM') $ordreDecision = 31;
    elseif ($decisionReelle === 'DEF') $ordreDecision = 32;
    
    // Normalisation du nom de la formation pour l'affichage (avec fallback sur contexte)
    $formationAffichee = normaliseFormation($etu['formation'], $context);
    
    return [
        'nip' => $etu['code_nip'],
        'scodoc_id' => $etu['etudid_scodoc'] ?? null,
        'decision' => $decisionAffichee ?: 'En cours',
        'formation' => $formationAffichee,
        'annee' => $etu['annee_scolaire'] ?? '',
        'semestre' => 'S' . $sem,
        'ordre' => $ordreDecision
    ];
}
?>
