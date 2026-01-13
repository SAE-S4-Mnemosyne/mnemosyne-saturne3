<?php
/**
 * API pour récupérer les données depuis ScoDoc
 * Documentation: https://scodoc.org/ScoDoc9API/
 * 
 * AUTHENTIFICATION:
 * 1. POST /ScoDoc/api/tokens avec Basic Auth (user:password) → reçoit un JWT
 * 2. Utiliser le JWT comme Bearer token pour toutes les requêtes suivantes
 */
header('Content-Type: application/json');
require_once '../config.php';

// Configuration ScoDoc - À ADAPTER SELON VOTRE INSTANCE
// L'URL de base doit être de la forme: https://scodoc.votre-iut.fr/ScoDoc
define('SCODOC_BASE_URL', 'https://scodoc.iutv.univ-paris13.fr/ScoDoc'); // Exemple IUT Villetaneuse
define('SCODOC_USER', ''); // Nom d'utilisateur ScoDoc avec permission ScoView
define('SCODOC_PASSWORD', ''); // Mot de passe de cet utilisateur

/**
 * Obtient un token JWT depuis l'API ScoDoc
 * Route: POST /ScoDoc/api/tokens
 */
function getScoDocToken() {
    if (empty(SCODOC_USER) || empty(SCODOC_PASSWORD)) {
        return ['error' => 'Identifiants ScoDoc non configurés'];
    }
    
    $url = SCODOC_BASE_URL . '/api/tokens';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, SCODOC_USER . ':' . SCODOC_PASSWORD); // Basic Auth
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Désactiver vérif SSL pour dev
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'Erreur CURL: ' . $error];
    }
    
    if ($httpCode === 401) {
        return ['error' => 'Authentification refusée - vérifiez user/password'];
    }
    
    if ($httpCode !== 200) {
        return ['error' => "Erreur HTTP $httpCode: $response"];
    }
    
    $data = json_decode($response, true);
    return $data['token'] ?? ['error' => 'Token non reçu'];
}

/**
 * Appelle une fonction de l'API ScoDoc avec le token JWT
 */
function callScoDocAPI($endpoint, $token) {
    $url = SCODOC_BASE_URL . '/api' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'Erreur CURL: ' . $error];
    }
    
    if ($httpCode === 401) {
        return ['error' => 'Token invalide ou expiré'];
    }
    
    if ($httpCode === 403) {
        return ['error' => 'Permission insuffisante pour cette action'];
    }
    
    if ($httpCode !== 200) {
        return ['error' => "Erreur HTTP $httpCode"];
    }
    
    return json_decode($response, true);
}

/**
 * Synchronise les données depuis ScoDoc vers la BDD locale
 */
function syncFromScoDoc($pdo) {
    $results = [
        'departements' => 0,
        'formations' => 0,
        'semestres' => 0,
        'etudiants' => 0,
        'errors' => []
    ];
    
    // 1. Obtenir le token
    $token = getScoDocToken();
    if (is_array($token) && isset($token['error'])) {
        return $token;
    }
    
    try {
        // 2. Récupérer les départements
        $depts = callScoDocAPI('/departements', $token);
        if (isset($depts['error'])) {
            $results['errors'][] = 'Départements: ' . $depts['error'];
        } else {
            foreach ($depts as $dept) {
                $stmt = $pdo->prepare("INSERT INTO departement (id_dept, acronyme, nom_complet) 
                                       VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE nom_complet = VALUES(nom_complet)");
                $stmt->execute([$dept['id'], $dept['acronyme'], $dept['description'] ?? $dept['acronyme']]);
                $results['departements']++;
            }
        }
        
        // 3. Pour chaque département, récupérer les formsemestres
        foreach ($depts as $dept) {
            $formsemestres = callScoDocAPI("/departement/{$dept['acronyme']}/formsemestres", $token);
            if (!isset($formsemestres['error'])) {
                foreach ($formsemestres as $fs) {
                    // Insérer la formation si elle n'existe pas
                    $stmt = $pdo->prepare("INSERT IGNORE INTO formation (id_formation, id_dept, titre) VALUES (?, ?, ?)");
                    $stmt->execute([$fs['formation_id'], $dept['id'], $fs['titre'] ?? 'Formation']);
                    $results['formations']++;
                    
                    // Insérer le semestre instance
                    $stmt = $pdo->prepare("INSERT INTO semestre_instance (id_formsemestre, id_formation, annee_scolaire, numero_semestre)
                                           VALUES (?, ?, ?, ?)
                                           ON DUPLICATE KEY UPDATE annee_scolaire = VALUES(annee_scolaire)");
                    $anneeScolaire = substr($fs['date_debut'] ?? '', 0, 4);
                    $stmt->execute([$fs['id'], $fs['formation_id'], $anneeScolaire, $fs['semestre_id'] ?? 1]);
                    $results['semestres']++;
                    
                    // 4. Récupérer les étudiants du semestre
                    $etudiants = callScoDocAPI("/formsemestre/{$fs['id']}/etudiants", $token);
                    if (!isset($etudiants['error'])) {
                        foreach ($etudiants as $etu) {
                            // Insérer l'étudiant
                            $stmt = $pdo->prepare("INSERT INTO etudiant (code_nip, code_ine, etudid_scodoc) 
                                                   VALUES (?, ?, ?)
                                                   ON DUPLICATE KEY UPDATE etudid_scodoc = VALUES(etudid_scodoc)");
                            $stmt->execute([$etu['code_nip'], $etu['code_ine'] ?? null, $etu['id']]);
                            
                            // Insérer l'inscription
                            $stmt = $pdo->prepare("INSERT IGNORE INTO inscription (code_nip, id_formsemestre, etat_inscription) VALUES (?, ?, ?)");
                            $stmt->execute([$etu['code_nip'], $fs['id'], $etu['etat'] ?? 'I']);
                            $results['etudiants']++;
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

// Point d'entrée API
$action = $_GET['action'] ?? 'status';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'sync':
            // Synchronisation complète depuis ScoDoc
            $result = syncFromScoDoc($pdo);
            echo json_encode(['status' => empty($result['errors']) ? 'success' : 'partial', 'data' => $result]);
            break;
            
        case 'test':
            // Test de connexion à l'API ScoDoc
            $token = getScoDocToken();
            if (is_array($token) && isset($token['error'])) {
                echo json_encode(['status' => 'error', 'message' => $token['error']]);
            } else {
                // Tester un appel simple
                $depts = callScoDocAPI('/departements', $token);
                echo json_encode([
                    'status' => isset($depts['error']) ? 'error' : 'success',
                    'token_obtained' => true,
                    'departements' => $depts
                ]);
            }
            break;
            
        case 'status':
        default:
            // Statut de la configuration
            echo json_encode([
                'configured' => !empty(SCODOC_USER) && !empty(SCODOC_PASSWORD),
                'base_url' => SCODOC_BASE_URL,
                'user' => SCODOC_USER ?: '(non configuré)',
                'help' => 'Configurez SCODOC_USER et SCODOC_PASSWORD dans api/scodoc.php',
                'doc' => 'https://scodoc.org/ScoDoc9API/'
            ]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur BDD: ' . $e->getMessage()]);
}
