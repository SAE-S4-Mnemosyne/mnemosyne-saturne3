<?php
/**
 * API pour générer le diagramme Sankey - Suivi de cohorte
 * 
 * LOGIQUE :
 * - Alternance et Formation Initiale = même formation (pas de différenciation)
 * - Passerelles comptées dans les entrées BUT2 (pas dans Nouveaux inscrits)
 * - Filtrage strict des diplômés (uniquement ADM)
 * 
 * Format: BUT1 → BUT2 → BUT3 → Diplômé/En cours
 */
header('Content-Type: application/json');
require_once '../config.php';
require_once 'utilitaires.php'; // Inclusion des fonctions utilitaires partagées

// Convertir toutes les erreurs (Avertissements, Notices) en Exceptions
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Fonction pour capturer les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        // Effacer toute sortie précédente (ex: JSON cassé)
        if (ob_get_length()) ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode(['error' => "Erreur fatale PHP: {$error['message']} ligne {$error['line']}"]);
        exit;
    }
});

// Tamponner la sortie pour éviter les avertissements inattendus avant le JSON
ob_start();

try {
    $formationTitre = $_GET['formation'] ?? '';
    $anneeDebut = $_GET['annee'] ?? '';
    $filterRegime = $_GET['regime'] ?? 'ALL'; // FI, FA, ALL
    $filterStatus = $_GET['status'] ?? 'ALL'; // PASS_OK, PASS_DEBT, FAIL, ALL

    if (!$formationTitre || !$anneeDebut) {
        echo json_encode(['error' => 'Paramètres manquants']);
        exit;
    }

    // Historique global des étudiants
    $globalHistory = []; // [nip => ['decisions' => [], 'regimes' => []]]

    function recordHistory($nip, $decisionCat, $regime) {
        global $globalHistory;
        if (!isset($globalHistory[$nip])) {
            $globalHistory[$nip] = ['decisions' => [], 'regimes' => []];
        }
        // Ajouter sans doublons
        if ($decisionCat && !in_array($decisionCat, $globalHistory[$nip]['decisions'])) {
            $globalHistory[$nip]['decisions'][] = $decisionCat;
        }
        if ($regime && !in_array($regime, $globalHistory[$nip]['regimes'])) {
            $globalHistory[$nip]['regimes'][] = $regime;
        }
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $isAllFormations = ($formationTitre === '__ALL__');
    $flows = [];
    
    /**
     * CHARGEMENT DES MAPPINGS ET SCÉNARIOS
     * Ces configurations permettent à l'admin de personnaliser l'affichage
     */
    $mappings = [];  // Code ScoDoc -> Libellé affiché
    $scenarios = []; // Formation source + cible -> Type de flux
    
    try {
        // Charger les mappings (table mapping_codes)
        $stmtMapping = $pdo->query("SELECT code_scodoc, libelle_graphique FROM mapping_codes");
        while ($row = $stmtMapping->fetch(PDO::FETCH_ASSOC)) {
            $mappings[$row['code_scodoc']] = $row['libelle_graphique'];
        }
    } catch (Exception $e) {
        // Table n'existe pas encore, pas grave
    }
    
    try {
        // Charger les scénarios (table scenario_correspondance)
        $stmtScenario = $pdo->query("SELECT formation_source, formation_cible, type_flux FROM scenario_correspondance");
        while ($row = $stmtScenario->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['formation_source'] . '||' . $row['formation_cible'];
            $scenarios[$key] = $row['type_flux'];
        }
    } catch (Exception $e) {
        // Table n'existe pas encore, pas grave
    }
    
    /**
     * Fonction pour appliquer le mapping à un libellé
     */
    $appliquerMapping = function($label) use ($mappings) {
        // Si un mapping exact existe, l'utiliser
        if (isset($mappings[$label])) {
            return $mappings[$label];
        }
        // Sinon, chercher un mapping partiel
        foreach ($mappings as $code => $libelle) {
            if (stripos($label, $code) !== false) {
                return str_ireplace($code, $libelle, $label);
            }
        }
        return $label;
    };
    
    // Année actuelle pour déterminer si la promo est "en cours"
    $anneeActuelle = (int)date('Y');
    $moisActuel = (int)date('m');
    if ($moisActuel < 9) {
        $anneeActuelle--;
    }
    
    // Calculer les années pour chaque niveau BUT
    $anneeBUT1 = (int)$anneeDebut;
    $anneeBUT2 = $anneeBUT1 + 1;
    $anneeBUT3 = $anneeBUT1 + 2;
    
    // Déterminer le niveau actuel de la promo
    $niveauActuel = 4; // Par défaut : terminée
    if ($anneeBUT1 >= $anneeActuelle) {
        $niveauActuel = 1;
    } elseif ($anneeBUT2 >= $anneeActuelle) {
        $niveauActuel = 2;
    } elseif ($anneeBUT3 >= $anneeActuelle) {
        $niveauActuel = 3;
    }
    
    /**
     * ÉTAPE 1 : Récupérer tous les étudiants BUT1 de l'année sélectionnée
     */
    $sqlBUT1 = "
        SELECT DISTINCT 
            i.code_nip, 
            f.titre as formation,
            f.code_scodoc as code_formation,
            i.decision_jury,
            i.etat_inscription,
            si.numero_semestre,
            si.annee_scolaire
        FROM Inscription i
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN Formation f ON si.id_formation = f.id_formation
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
    ";
    $stmt = $pdo->prepare($sqlBUT1);
    $stmt->execute(["$anneeBUT1%"]);
    $tousEtudiantsBUT1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Identifier les redoublants entrants en BUT1
    $anneeN1 = $anneeBUT1 - 1;
    $sqlRedoublantsBUT1 = "
        SELECT DISTINCT i.code_nip
        FROM Inscription i
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
    ";
    $stmt = $pdo->prepare($sqlRedoublantsBUT1);
    $stmt->execute(["$anneeN1%"]);
    $redoublantsEntrantsBUT1 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $setRedoublantsEntrants = array_flip($redoublantsEntrantsBUT1);


    // Filtrer par formation (avec normalisation) et enregistrer l'historique
    $etudiantsBUT1Filtres = [];
    foreach ($tousEtudiantsBUT1 as $etu) {
        // Garantir des chaînes de caractères (compatibilité PHP 8.1+)
        $formationDB = $etu['formation'] ?? '';
        $nip = $etu['code_nip'] ?? '';
        
        if ($isAllFormations || matchFormation($formationDB, $formationTitre)) {
            $decisionCat = categorizeDecision($etu['decision_jury'] ?? '');
            $regime = detectRegime($formationDB, $etu['code_formation'] ?? '');

            recordHistory($nip, $decisionCat, $regime);
            
            // SI c'est un redoublant entrant, il a forcément un "FAIL" dans son passé (Année N-1)
            // On l'ajoute explicitement à l'historique pour le filtre "Echecs / Redoublements"
            if (isset($setRedoublantsEntrants[$nip])) {
                recordHistory($nip, 'FAIL', null);
            }
            
            $etudiantsBUT1Filtres[] = $etu;
        }
    }
    
    // Créer un index par code_nip avec priorité au S2
    $but1ByNip = [];
    foreach ($etudiantsBUT1Filtres as $etu) {
        $nip = $etu['code_nip'];
        $sem = (int)$etu['numero_semestre'];
        if (!isset($but1ByNip[$nip]) || $sem > $but1ByNip[$nip]['numero_semestre']) {
            $but1ByNip[$nip] = $etu;
        }
    }
    
    $totalBUT1 = count($but1ByNip);
    
    if ($totalBUT1 == 0) {
        echo json_encode([
            'nodes' => [], 
            'links' => [], 
            'stats' => ['total' => 0], 
            'message' => 'Aucun étudiant BUT1 trouvé pour cette année'
        ]);
        exit;
    }
    
    // Compteurs BUT1 (Listes de NIPs)
    $listPassageBUT2 = [];
    $listRedoublementBUT1 = [];
    $listAbandonBUT1 = [];
    $nipsBUT2 = []; // Utilisé pour le suivi vers BUT2

    $setRedoublantsEntrants = is_array($setRedoublantsEntrants) ? $setRedoublantsEntrants : [];
    
    $listRedoublantsEntrants = [];
    $listNouveauxBUT1 = [];

    // Analyser les décisions de jury pour BUT1
    foreach ($but1ByNip as $nip => $etu) {
        // Compter les entrées
        if (isset($setRedoublantsEntrants[$nip])) {
            $listRedoublantsEntrants[] = $nip;
        } else {
            $listNouveauxBUT1[] = $nip;
        }
        
        $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
        $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
        
        // Catégorisation des décisions
        if ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM') {
            $listAbandonBUT1[] = $nip;
        } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ' || strpos($decision, 'REDOUB') !== false) {
            $listRedoublementBUT1[] = $nip;
        } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ') {
            $listPassageBUT2[] = $nip;
            $nipsBUT2[] = $nip;
        } else {
            if ($niveauActuel == 1) {
                // En cours de BUT1
            } else {
                $listPassageBUT2[] = $nip;
                $nipsBUT2[] = $nip;
            }
        }
    }

    // Créer les flux d'entrée BUT1
    if (count($listRedoublantsEntrants) > 0) {
        $flows["Redoublant||BUT1"] = $listRedoublantsEntrants;
    }
    if (count($listNouveauxBUT1) > 0) {
        $flows["Nouveaux inscrits||BUT1"] = $listNouveauxBUT1;
    }
    
    // Flux BUT1 vers destinations
    if (count($listPassageBUT2) > 0) $flows["BUT1||BUT2"] = $listPassageBUT2;
    if (count($listRedoublementBUT1) > 0) $flows["BUT1||Redoublement BUT1"] = $listRedoublementBUT1;
    if (count($listAbandonBUT1) > 0) $flows["BUT1||Abandon BUT1"] = $listAbandonBUT1;
    
    // Si la promo est en BUT1
    $listEnCoursBUT1 = [];
    if ($niveauActuel == 1) {
        // En cours calculé par différence
        $othersUtils = array_merge($listAbandonBUT1, $listRedoublementBUT1);
        $othersMap = array_flip($othersUtils);
        foreach ($but1ByNip as $nip => $e) {
            if (!isset($othersMap[$nip])) {
                $listEnCoursBUT1[] = $nip;
            }
        }
        
        if (count($listEnCoursBUT1) > 0) {
            $flows["BUT1||En cours BUT1"] = $listEnCoursBUT1;
        }
    }

    /**
     * ÉTAPE 2 : BUT2 - Suivre les étudiants + détecter passerelles
     */
    $listPassageBUT3 = [];
    $listRedoublementBUT2 = [];
    $listAbandonBUT2 = [];
    $listReorientationBUT2 = [];
    $nipsBUT3 = [];
    $listPasserellesBUT2 = [];
    
    // Récupérer TOUS les étudiants BUT2 de l'année BUT2
    $sqlTousBUT2 = "
        SELECT DISTINCT 
            i.code_nip, 
            f.titre as formation,
            f.code_scodoc as code_formation,
            i.decision_jury,
            i.etat_inscription,
            si.numero_semestre
        FROM Inscription i
        JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN Formation f ON si.id_formation = f.id_formation
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 3 OR si.numero_semestre = 4)
    ";
    $stmt = $pdo->prepare($sqlTousBUT2);
    $stmt->execute(["$anneeBUT2%"]);
    $tousEtudiantsBUT2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrer par formation et créer index, enregistrer l'historique
    $but2ByNip = [];
    foreach ($tousEtudiantsBUT2 as $etu) {
        $formationDB = $etu['formation'] ?? '';
        $nip = $etu['code_nip'] ?? '';

        if ($isAllFormations || matchFormation($formationDB, $formationTitre)) {
            $decisionCat = categorizeDecision($etu['decision_jury'] ?? '');
            $regime = detectRegime($formationDB, $etu['code_formation'] ?? '');

            recordHistory($nip, $decisionCat, $regime);

            $sem = (int)$etu['numero_semestre'];
            if (!isset($but2ByNip[$nip]) || $sem > $but2ByNip[$nip]['numero_semestre']) {
                $but2ByNip[$nip] = $etu;
            }
        }
    }
    
    // Compter les passerelles (en BUT2 mais pas dans notre cohorte BUT1)
    $setNipsBUT1 = array_flip(array_keys($but1ByNip));
    foreach ($but2ByNip as $nip => $etu) {
        if (!isset($setNipsBUT1[$nip])) {
            $listPasserellesBUT2[] = $nip;
        }
    }
    
    // Analyser les étudiants de notre cohorte passés en BUT2
    if (!empty($nipsBUT2) && $niveauActuel >= 2) {
        foreach ($nipsBUT2 as $nip) {
            if (isset($but2ByNip[$nip])) {
                $etu = $but2ByNip[$nip];
                $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
                $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
                
                if ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM') {
                    $listAbandonBUT2[] = $nip;
                } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ' || strpos($decision, 'REDOUB') !== false) {
                    $listRedoublementBUT2[] = $nip;
                } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ') {
                    $listPassageBUT3[] = $nip;
                    $nipsBUT3[] = $nip;
                } else {
                    if ($niveauActuel == 2) {
                        // En cours de BUT2
                    } else {
                        $listPassageBUT3[] = $nip;
                        $nipsBUT3[] = $nip;
                    }
                }
            } else {
                // Pas trouvé en BUT2
                if ($niveauActuel > 2) {
                    // Promo passée, on suppose le passage
                    $listPassageBUT3[] = $nip;
                    $nipsBUT3[] = $nip;
                }
            }
        }
    }
    
    // Flux des passerelles vers BUT2
    if (count($listPasserellesBUT2) > 0 && $niveauActuel >= 2) {
        $flows["Passerelle||BUT2"] = $listPasserellesBUT2;
    }
    
    // Flux BUT2 vers destinations (seulement si promo niveau > 2)
    if (count($listPassageBUT3) > 0 && $niveauActuel > 2) $flows["BUT2||BUT3"] = $listPassageBUT3;
    if (count($listRedoublementBUT2) > 0) $flows["BUT2||Redoublement BUT2"] = $listRedoublementBUT2;
    if (count($listAbandonBUT2) > 0) $flows["BUT2||Abandon BUT2"] = $listAbandonBUT2;
    
    if (count($listReorientationBUT2) > 0 && $niveauActuel > 2) $flows["BUT2||Réorientation"] = $listReorientationBUT2;
    
    // Si promo en BUT2 - calculer les étudiants en cours
    $listEnCoursBUT2 = [];
    if ($niveauActuel == 2 && !empty($nipsBUT2)) {
        $knownExits = array_merge($listAbandonBUT2, $listRedoublementBUT2);
        $exitMap = array_flip($knownExits);
        foreach ($nipsBUT2 as $nip) {
            if (!isset($exitMap[$nip])) {
                $listEnCoursBUT2[] = $nip;
            }
        }
        if (count($listEnCoursBUT2) > 0) {
            $flows["BUT2||En cours BUT2"] = $listEnCoursBUT2;
        }
    }
    
    /**
     * ÉTAPE 3 : BUT3 - Suivre les étudiants
     */
    $listDiplome = [];
    $listRedoublementBUT3 = [];
    $listAbandonBUT3 = [];
    $listEnCoursBUT3 = [];
    
    if (!empty($nipsBUT3) && $niveauActuel >= 3) {
        // Récupérer tous les étudiants BUT3
        $sqlBUT3 = "
            SELECT DISTINCT 
                i.code_nip, 
                f.titre as formation,
                f.code_scodoc as code_formation,
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM Inscription i
            JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN Formation f ON si.id_formation = f.id_formation
            WHERE si.annee_scolaire LIKE ?
            AND (si.numero_semestre = 5 OR si.numero_semestre = 6)
        ";
        $stmt = $pdo->prepare($sqlBUT3);
        $stmt->execute(["$anneeBUT3%"]);
        $tousEtudiantsBUT3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrer par formation et créer index
        $but3ByNip = [];
        foreach ($tousEtudiantsBUT3 as $etu) {
            $formationDB = $etu['formation'] ?? '';
            $nip = $etu['code_nip'] ?? '';

            if ($isAllFormations || matchFormation($formationDB, $formationTitre)) {
                $sem = (int)$etu['numero_semestre'];
                
                // Enregistrement Historique
                $decisionCat = categorizeDecision($etu['decision_jury'] ?? '');
                $regime = detectRegime($formationDB, $etu['code_formation'] ?? '');

                recordHistory($nip, $decisionCat, $regime);
                
                if (!isset($but3ByNip[$nip]) || $sem > $but3ByNip[$nip]['numero_semestre']) {
                    $but3ByNip[$nip] = $etu;
                }
            }
        }
        
        foreach ($nipsBUT3 as $nip) {
            if (isset($but3ByNip[$nip])) {
                $etu = $but3ByNip[$nip];
                $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
                $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
                
                if ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM') {
                    $listAbandonBUT3[] = $nip;
                } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ' || strpos($decision, 'REDOUB') !== false) {
                    $listRedoublementBUT3[] = $nip;
                } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'CMP' || $decision === 'ADJ') {
                    // Diplômé
                    $listDiplome[] = $nip;
                } else {
                    if ($niveauActuel == 3) {
                        $listEnCoursBUT3[] = $nip;
                    } else {
                        $listDiplome[] = $nip;
                    }
                }
            } else {
                if ($niveauActuel > 3) {
                    $listDiplome[] = $nip;
                } elseif ($niveauActuel == 3) {
                    $listEnCoursBUT3[] = $nip;
                }
            }
        }
    }
    
    // Flux BUT3 vers destinations
    if (count($listDiplome) > 0) $flows["BUT3||Diplômé"] = $listDiplome;
    if (count($listRedoublementBUT3) > 0) $flows["BUT3||Redoublement BUT3"] = $listRedoublementBUT3;
    if (count($listAbandonBUT3) > 0) $flows["BUT3||Abandon BUT3"] = $listAbandonBUT3;
    if (count($listEnCoursBUT3) > 0) $flows["BUT3||En cours BUT3"] = $listEnCoursBUT3;

    /**
     * CONSTRUCTION DU RÉSULTAT
     * Ordre des liens important pour le positionnement vertical dans Sankey :
     * 1. Flux principaux (passage vers niveau suivant)
     * 2. Flux "en cours"
     * 3. Flux négatifs (abandons, redoublements) en dernier = en bas
     */
    $nodes = [];
    $links = [];
    
    // Ordre de priorité des types de flux
    $ordreFlux = [
        'Nouveaux inscrits' => 1,
        'Passerelle' => 2,
        'Redoublant' => 3,
        'BUT1' => 10,
        'BUT2' => 20,
        'BUT3' => 30,
        'Diplômé' => 40,
        'En cours' => 50,
        'Réorientation' => 90,
        'Redoublement' => 95,
        'Abandon' => 99,
    ];
    
    // Fonction pour obtenir la priorité d'un flux
    $getPriorite = function($source, $target) use ($ordreFlux) {
        $prio = 50; // Par défaut
        foreach ($ordreFlux as $mot => $val) {
            if (strpos($target, $mot) !== false) {
                $prio = $val;
                break;
            }
        }
        // Grouper les flux négatifs par niveau
        if (strpos($target, 'Abandon') !== false || strpos($target, 'Redoublement') !== false) {
            if (strpos($source, 'BUT1') !== false) $prio += 1;
            elseif (strpos($source, 'BUT2') !== false) $prio += 2;
            elseif (strpos($source, 'BUT3') !== false) $prio += 3;
        }
        return $prio;
    };
    
    // --- FILTRAGE FINAL (Basé sur la Trajectoire) ---
    // On ne garde que les étudiants qui satisfont le critère *au moins une fois* dans leur parcours.
    
    $keptNips = [];
    foreach ($globalHistory as $nip => $hist) {
        $keep = true;
        
        // Filtre Régime
        if ($filterRegime === 'FA') {
            if (!in_array('FA', $hist['regimes'])) $keep = false;
        } elseif ($filterRegime === 'FI') {
             if (in_array('FA', $hist['regimes'])) $keep = false; 
        }

        // Filtre Statut
        if ($keep && !empty($filterStatus)) {
            if ($filterStatus === 'PASS_DEBT') {
                if (!in_array('PASS_DEBT', $hist['decisions'])) $keep = false;
            } elseif ($filterStatus === 'FAIL') {
                if (!in_array('FAIL', $hist['decisions'])) $keep = false;
            } elseif ($filterStatus === 'PASS_OK') {
                if (in_array('PASS_DEBT', $hist['decisions']) || in_array('FAIL', $hist['decisions'])) $keep = false;
            }
        }

        if ($keep) {
            $keptNips[$nip] = true;
        }
    }
    
    // Construire les liens avec priorité ET appliquer le mapping
    $linksAvecPrio = [];
    foreach ($flows as $key => $nipList) {
        // Filtrer la liste des étudiants
        $filteredList = [];
        if (is_array($nipList)) {
            foreach ($nipList as $snip) {
                if (isset($keptNips[$snip])) {
                    $filteredList[] = $snip;
                }
            }
        }
        $count = count($filteredList);

        if ($count > 0) {
            list($source, $target) = explode("||", $key);
            
            // Appliquer le mapping aux libellés
            $sourceMapped = $appliquerMapping($source);
            $targetMapped = $appliquerMapping($target);
            
            $linksAvecPrio[] = [
                'source' => $sourceMapped, 
                'target' => $targetMapped, 
                'value' => $count,
                'students' => $filteredList, // IMPORTANT: Envoi de la liste des étudiants
                'prio' => $getPriorite($source, $target)
            ];
            $nodes[$sourceMapped] = true;
            $nodes[$targetMapped] = true;
        }
    }
    
    // Trier par priorité
    usort($linksAvecPrio, function($a, $b) {
        return $a['prio'] - $b['prio'];
    });
    
    // Retirer la priorité pour le résultat final
    foreach ($linksAvecPrio as $l) {
        $links[] = [
            'source' => $l['source'], 
            'target' => $l['target'], 
            'value' => $l['value'],
            'students' => $l['students']
        ];
    }
    
    $nodeList = [];
    foreach (array_keys($nodes) as $n) {
        $nodeList[] = ['name' => $n];
    }
    
    // Statistiques
    $totalRedoublement = count($listRedoublementBUT1) + count($listRedoublementBUT2) + count($listRedoublementBUT3);
    $totalAbandon = count($listAbandonBUT1) + count($listAbandonBUT2) + count($listAbandonBUT3);
    
    // Calcul En cours
    $nbEnCoursBUT1 = count($listEnCoursBUT1);
    $nbEnCoursBUT2 = isset($listEnCoursBUT2) ? count($listEnCoursBUT2) : 0;
    $nbEnCoursBUT3 = count($listEnCoursBUT3);
    $totalEnCours = $nbEnCoursBUT1 + $nbEnCoursBUT2 + $nbEnCoursBUT3;
    
    // Statut de la promo
    $statutPromo = "terminée";
    if ($niveauActuel == 1) $statutPromo = "en BUT1";
    elseif ($niveauActuel == 2) $statutPromo = "en BUT2";
    elseif ($niveauActuel == 3) $statutPromo = "en BUT3";
    
    $stats = [
        'valide' => count($listDiplome),
        'partiel' => $totalEnCours,
        'redoublement' => $totalRedoublement + count($listRedoublantsEntrants),
        'abandon' => $totalAbandon,
        'total' => $totalBUT1,
        'statutPromo' => $statutPromo,
        'niveauActuel' => $niveauActuel
    ];

    echo json_encode([
        'nodes' => $nodeList,
        'links' => $links,
        'stats' => $stats
    ]);

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => "Erreur PHP: " . $e->getMessage() . " dans " . basename($e->getFile()) . ":" . $e->getLine()]);
}
ob_end_flush();
