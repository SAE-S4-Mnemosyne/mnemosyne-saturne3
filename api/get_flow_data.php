<?php
/**
 * API pour générer le diagramme Sankey - Suivi de cohorte
 * 
 * VERSION CORRIGÉE :
 * - Alternance et Formation Initiale = même formation (pas de différenciation)
 * - Passerelles comptées dans les entrées BUT2 (pas dans Nouveaux inscrits)
 * - Filtrage strict des diplômés (uniquement ADM)
 * 
 * Format: BUT1 → BUT2 → BUT3 → Diplômé/En cours
 */
header('Content-Type: application/json');
require_once '../config.php';

$formationTitre = $_GET['formation'] ?? '';
$anneeDebut = $_GET['annee'] ?? '';

if (!$formationTitre || !$anneeDebut) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

/**
 * Fonction pour normaliser le nom de formation
 * (supprime les mentions Alternance, Apprentissage, FI, FA, etc.)
 */
function normaliseFormation($titre) {
    // Extraire le type de BUT principal (GEA, INFO, SD, etc.)
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
    return $titre; // Retourne le titre original si pas de match
}

/**
 * Fonction pour vérifier si un titre de formation correspond
 */
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
            i.decision_jury,
            i.etat_inscription,
            si.numero_semestre,
            si.annee_scolaire
        FROM inscription i
        JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN formation f ON si.id_formation = f.id_formation
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
    ";
    $stmt = $pdo->prepare($sqlBUT1);
    $stmt->execute(["$anneeBUT1%"]);
    $tousEtudiantsBUT1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrer par formation (avec normalisation)
    $etudiantsBUT1Filtres = [];
    foreach ($tousEtudiantsBUT1 as $etu) {
        if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
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
    
    // Compteurs BUT1
    $passageBUT2 = 0;
    $redoublementBUT1 = 0;
    $abandonBUT1 = 0;
    $nipsBUT2 = [];
    
    // Identifier les redoublants entrants en BUT1
    $anneeN1 = $anneeBUT1 - 1;
    $sqlRedoublantsBUT1 = "
        SELECT DISTINCT i.code_nip
        FROM inscription i
        JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 1 OR si.numero_semestre = 2)
    ";
    $stmt = $pdo->prepare($sqlRedoublantsBUT1);
    $stmt->execute(["$anneeN1%"]);
    $redoublantsEntrantsBUT1 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $setRedoublantsEntrants = array_flip($redoublantsEntrantsBUT1);
    
    $nbRedoublantsEntrants = 0;
    $nbNouveauxBUT1 = 0;
    
    // Analyser les décisions de jury pour BUT1
    foreach ($but1ByNip as $nip => $etu) {
        // Compter les entrées
        if (isset($setRedoublantsEntrants[$nip])) {
            $nbRedoublantsEntrants++;
        } else {
            $nbNouveauxBUT1++;
        }
        
        $decision = strtoupper(trim($etu['decision_jury'] ?? ''));
        $etat = strtoupper(trim($etu['etat_inscription'] ?? ''));
        
        // Catégorisation des décisions
        if ($etat === 'D' || $decision === 'DEF' || $decision === 'NAR' || $decision === 'DEM') {
            $abandonBUT1++;
        } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ') {
            $redoublementBUT1++;
        } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ') {
            $passageBUT2++;
            $nipsBUT2[] = $nip;
        } else {
            if ($niveauActuel == 1) {
                // En cours de BUT1
            } else {
                $passageBUT2++;
                $nipsBUT2[] = $nip;
            }
        }
    }
    
    // Créer les flux d'entrée BUT1
    if ($nbRedoublantsEntrants > 0) {
        $flows["Redoublant||BUT1"] = $nbRedoublantsEntrants;
    }
    if ($nbNouveauxBUT1 > 0) {
        $flows["Nouveaux inscrits||BUT1"] = $nbNouveauxBUT1;
    }
    
    // Flux BUT1 vers destinations
    if ($passageBUT2 > 0) $flows["BUT1||BUT2"] = $passageBUT2;
    if ($redoublementBUT1 > 0) $flows["BUT1||Redoublement BUT1"] = $redoublementBUT1;
    if ($abandonBUT1 > 0) $flows["BUT1||Abandon BUT1"] = $abandonBUT1;
    
    // Si la promo est en BUT1
    $enCoursBUT1 = 0;
    if ($niveauActuel == 1) {
        $enCoursBUT1 = $totalBUT1 - $abandonBUT1 - $redoublementBUT1;
        if ($enCoursBUT1 > 0) {
            $flows["BUT1||En cours BUT1"] = $enCoursBUT1;
        }
    }
    
    /**
     * ÉTAPE 2 : BUT2 - Suivre les étudiants + détecter passerelles
     */
    $passageBUT3 = 0;
    $redoublementBUT2 = 0;
    $abandonBUT2 = 0;
    $reorientationBUT2 = 0;
    $nipsBUT3 = [];
    $passerellesBUT2 = 0;
    
    // Récupérer TOUS les étudiants BUT2 de l'année BUT2
    $sqlTousBUT2 = "
        SELECT DISTINCT 
            i.code_nip, 
            f.titre as formation,
            i.decision_jury,
            i.etat_inscription,
            si.numero_semestre
        FROM inscription i
        JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
        JOIN formation f ON si.id_formation = f.id_formation
        WHERE si.annee_scolaire LIKE ?
        AND (si.numero_semestre = 3 OR si.numero_semestre = 4)
    ";
    $stmt = $pdo->prepare($sqlTousBUT2);
    $stmt->execute(["$anneeBUT2%"]);
    $tousEtudiantsBUT2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrer par formation et créer index
    $but2ByNip = [];
    foreach ($tousEtudiantsBUT2 as $etu) {
        if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
            $nip = $etu['code_nip'];
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
            $passerellesBUT2++;
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
                    $abandonBUT2++;
                } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ') {
                    $redoublementBUT2++;
                } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'PASD' || $decision === 'CMP' || $decision === 'ADJ') {
                    $passageBUT3++;
                    $nipsBUT3[] = $nip;
                } else {
                    if ($niveauActuel == 2) {
                        // En cours de BUT2 - pas de passage vers BUT3
                    } else {
                        $passageBUT3++;
                        $nipsBUT3[] = $nip;
                    }
                }
            } else {
                // Pas trouvé en BUT2
                // Si la promo est en cours (niveau 2), on ne compte pas comme réorientation
                // car ils peuvent être en cours mais pas encore dans la BDD
                if ($niveauActuel > 2) {
                    // Promo passée, on suppose passage
                    $passageBUT3++;
                    $nipsBUT3[] = $nip;
                }
                // Si niveauActuel == 2, on les ignore (seront comptés dans En cours BUT2)
            }
        }
    }
    
    // Flux des passerelles vers BUT2
    if ($passerellesBUT2 > 0 && $niveauActuel >= 2) {
        $flows["Passerelle||BUT2"] = $passerellesBUT2;
    }
    
    // Flux BUT2 vers destinations (seulement si promo niveau > 2)
    if ($passageBUT3 > 0 && $niveauActuel > 2) $flows["BUT2||BUT3"] = $passageBUT3;
    if ($redoublementBUT2 > 0) $flows["BUT2||Redoublement BUT2"] = $redoublementBUT2;
    if ($abandonBUT2 > 0) $flows["BUT2||Abandon BUT2"] = $abandonBUT2;
    // Réorientation seulement si la promo n'est plus en BUT2
    if ($reorientationBUT2 > 0 && $niveauActuel > 2) $flows["BUT2||Réorientation"] = $reorientationBUT2;
    
    // Si promo en BUT2 - calculer les étudiants en cours
    $enCoursBUT2 = 0;
    if ($niveauActuel == 2 && !empty($nipsBUT2)) {
        // Tous les étudiants passés de BUT1 moins abandons et redoublements = en cours
        $enCoursBUT2 = count($nipsBUT2) - $abandonBUT2 - $redoublementBUT2;
        if ($enCoursBUT2 > 0) {
            $flows["BUT2||En cours BUT2"] = $enCoursBUT2;
        }
    }
    
    /**
     * ÉTAPE 3 : BUT3 - Suivre les étudiants
     */
    $diplome = 0;
    $redoublementBUT3 = 0;
    $abandonBUT3 = 0;
    $enCoursBUT3 = 0;
    
    if (!empty($nipsBUT3) && $niveauActuel >= 3) {
        // Récupérer tous les étudiants BUT3
        $sqlBUT3 = "
            SELECT DISTINCT 
                i.code_nip, 
                f.titre as formation,
                i.decision_jury,
                i.etat_inscription,
                si.numero_semestre
            FROM inscription i
            JOIN semestre_instance si ON i.id_formsemestre = si.id_formsemestre
            JOIN formation f ON si.id_formation = f.id_formation
            WHERE si.annee_scolaire LIKE ?
            AND (si.numero_semestre = 5 OR si.numero_semestre = 6)
        ";
        $stmt = $pdo->prepare($sqlBUT3);
        $stmt->execute(["$anneeBUT3%"]);
        $tousEtudiantsBUT3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrer par formation et créer index
        $but3ByNip = [];
        foreach ($tousEtudiantsBUT3 as $etu) {
            if ($isAllFormations || matchFormation($etu['formation'], $formationTitre)) {
                $nip = $etu['code_nip'];
                $sem = (int)$etu['numero_semestre'];
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
                    $abandonBUT3++;
                } elseif ($decision === 'RED' || $decision === 'AJ' || $decision === 'ATJ') {
                    $redoublementBUT3++;
                } elseif ($decision === 'ADM' || $decision === 'ADSUP' || $decision === 'CMP' || $decision === 'ADJ') {
                    // Diplômé = uniquement ADM ou équivalent
                    $diplome++;
                } else {
                    if ($niveauActuel == 3) {
                        $enCoursBUT3++;
                    } else {
                        $diplome++;
                    }
                }
            } else {
                if ($niveauActuel > 3) {
                    $diplome++;
                } elseif ($niveauActuel == 3) {
                    $enCoursBUT3++;
                }
            }
        }
    }
    
    // Flux BUT3 vers destinations
    if ($diplome > 0) $flows["BUT3||Diplômé"] = $diplome;
    if ($redoublementBUT3 > 0) $flows["BUT3||Redoublement BUT3"] = $redoublementBUT3;
    if ($abandonBUT3 > 0) $flows["BUT3||Abandon BUT3"] = $abandonBUT3;
    if ($enCoursBUT3 > 0) $flows["BUT3||En cours BUT3"] = $enCoursBUT3;
    
    /**
     * CONSTRUCTION DU RÉSULTAT
     * Ordre des liens important pour le positionnement vertical dans Sankey :
     * 1. Flux principaux (passage vers niveau suivant)
     * 2. Flux "en cours"
     * 3. Flux négatifs (abandons, redoublements) en dernier = en bas
     */
    $nodes = [];
    $links = [];
    
    // Définir l'ordre de priorité des types de flux (bas = derniers ajoutés = en bas du graphique)
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
        // Si c'est un flux négatif, ajouter la source pour grouper par niveau
        if (strpos($target, 'Abandon') !== false || strpos($target, 'Redoublement') !== false) {
            if (strpos($source, 'BUT1') !== false) $prio += 1;
            elseif (strpos($source, 'BUT2') !== false) $prio += 2;
            elseif (strpos($source, 'BUT3') !== false) $prio += 3;
        }
        return $prio;
    };
    
    // Construire les liens avec priorité ET appliquer le mapping
    $linksAvecPrio = [];
    foreach ($flows as $key => $count) {
        if ($count > 0) {
            list($source, $target) = explode("||", $key);
            
            // Appliquer le mapping aux libellés
            $sourceMapped = $appliquerMapping($source);
            $targetMapped = $appliquerMapping($target);
            
            $linksAvecPrio[] = [
                'source' => $sourceMapped, 
                'target' => $targetMapped, 
                'value' => $count,
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
        $links[] = ['source' => $l['source'], 'target' => $l['target'], 'value' => $l['value']];
    }
    
    $nodeList = [];
    foreach (array_keys($nodes) as $n) {
        $nodeList[] = ['name' => $n];
    }
    
    // Statistiques
    $totalRedoublement = $redoublementBUT1 + $redoublementBUT2 + $redoublementBUT3;
    $totalAbandon = $abandonBUT1 + $abandonBUT2 + $abandonBUT3;
    $totalEnCours = $enCoursBUT1 + (isset($enCoursBUT2) ? $enCoursBUT2 : 0) + $enCoursBUT3;
    
    // Statut de la promo
    $statutPromo = "terminée";
    if ($niveauActuel == 1) $statutPromo = "en BUT1";
    elseif ($niveauActuel == 2) $statutPromo = "en BUT2";
    elseif ($niveauActuel == 3) $statutPromo = "en BUT3";
    
    $stats = [
        'valide' => $diplome,
        'partiel' => $totalEnCours,
        'redoublement' => $totalRedoublement,
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

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
