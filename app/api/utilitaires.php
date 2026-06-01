<?php
/**
 * helpers.php -> utilitaires.php
 * Fonctions utilitaires partagées pour l'API Mnémosyne
 * 
 * Contient :
 * - Normalisation des noms de formation
 * - Détection du régime (FI/FA)
 * - Catégorisation des décisions de jury
 * - Filtrage des étudiants
 * - Vérification de correspondance de formation
 */

/**
 * Fonction pour normaliser le nom de formation
 * (supprime les mentions Alternance, Apprentissage, FI, FA, etc.)
 * Accepte un contexte optionnel pour aider à la désambiguïsation
 */
function normaliseFormation($titre, $formationContext = null) {
    // 1. Pré-nettoyage : Remplacer "Bachelor..." par "BUT"
    $titre = preg_replace('/Bachelor\s+Universitaire\s+de\s+Technologie/ui', 'BUT', $titre);
    
    // 2. Identification par mots-clés (Ordre IMPORTANT)
    
    // GEII : "Electrique" ou "Industrielle" ou "GEII"
    if (preg_match('/(Electrique|Industrielle|GEII|G\.E\.I\.I)/ui', $titre)) return 'BUT GEII';
    
    // CJ : "Juridique" ou "CJ"
    if (preg_match('/(Juridique|CJ)/ui', $titre)) return 'BUT CJ';
    
    // INFO : "Informatique" ou "INFO"
    if (preg_match('/(Informatique|INFO)/ui', $titre)) return 'BUT INFO';
    
    // R&T : "Réseaux" ou "Télécom" ou "R&T"
    if (preg_match('/(R[eé]seaux|T[eé]l[eé]com|R\&T|R\.T)/ui', $titre)) return 'BUT R&T';
    
    // GEA : "Gestion" ou "GEA"
    if (preg_match('/(Gestion|GEA|G\.E\.A)/ui', $titre)) return 'BUT GEA';
    
    // SD : "Données" ou "Daniel" (si présent) ou "STID" ou "SD"
    if (preg_match('/(Donn[ée]es|STID|SD)/ui', $titre)) return 'BUT SD';
    
    // TC : "Commercialisation" ou "TC"
    if (preg_match('/(Commercialisation|TC|Tech.*Co)/ui', $titre)) return 'BUT TC';

    // 3. Utilisation du contexte si aucune correspondance précise
    if ($formationContext && $formationContext !== '__ALL__') {
        $contextNorm = normaliseFormation($formationContext);
        if ($contextNorm && $contextNorm !== 'Bachelor Universitaire de Technologie' && $contextNorm !== 'BUT') {
             return $contextNorm;
        }
    }
    
    return 'BUT'; // Fallback
}

/**
 * Fonction pour vérifier si un titre de formation correspond à la recherche
 */
function matchFormation($titreDB, $formationRecherche) {
    if ($formationRecherche === '__ALL__') return true;
    
    $typeDB = normaliseFormation($titreDB);
    $typeRecherche = normaliseFormation($formationRecherche);
    
    return $typeDB === $typeRecherche || 
           stripos($titreDB, $formationRecherche) !== false ||
           stripos($formationRecherche, $typeDB) !== false;
}

/**
 * Fonction pour détecter le régime (FI ou FA)
 */
function detectRegime($titre, $code = '') {
    $text = $titre . ' ' . $code;
    if (preg_match('/(alternance|apprentissage|contrat pro|professionnalisation|FC|continu|FA|ALT|APP)/ui', $text)) {
        return 'FA';
    }
    return 'FI';
}

/**
 * Fonction pour catégoriser la décision (Passage OK, Dette, Echec)
 */
function categorizeDecision($code) {
    $code = strtoupper(trim($code));
    if (in_array($code, ['ADM', 'ADSUP'])) return 'PASS_OK';
    if (in_array($code, ['CMP', 'ADJ', 'ATB', 'PASD'])) return 'PASS_DEBT'; // ATB, PASD (Passage sans diplome / avec dette)
    if (in_array($code, ['RED', 'AJ', 'ATJ', 'NAR', 'DEF', 'DEM', 'RAT'])) return 'FAIL';
    
    // Cas spécifiques redoublants entrants
    if (strpos($code, 'REDOUB') !== false) return 'FAIL';
    
    return 'UNKNOWN';
}

/**
 * Fonction générique de filtrage d'étudiant
 */
function shouldKeepStudent($etu, $regimeFilter, $statusFilter) {
    // 1. Filtre Régime
    if ($regimeFilter !== 'ALL') {
        $regime = detectRegime($etu['formation'], $etu['code_formation'] ?? '');
        if ($regime !== $regimeFilter) return false;
    }
    
    // 2. Filtre Statut
    if ($statusFilter !== 'ALL') {
        $decision = $etu['decision_jury'] ?? '';
        
        // Si aucune décision (En cours / ATT), on masque si un filtre est actif
        if (empty($decision) || $decision === 'ATT') return false;
        
        $cat = categorizeDecision($decision);
        if ($cat !== $statusFilter) return false;
    }
    
    return true;
}
?>
