// Scripte principale

<?php
//include 'index.php';

function scodoc_api_get(string $path){// recuperation de token d'authentification et de l'url de scodoc
    $token = scodoc_recupToken();
    $SC    = scodoc_Config(); 

    $url = rtrim($SC['urlAPI'], '/') . $path;

    $ch = curl_init();
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST           => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
    ];
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erreur cURL sur $url : $err");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("Erreur HTTP $httpCode sur $url");
    }
    return $response;
}

function scodoc_save_json(string $json, string $filename){// fonction qui prend du texte Json le rend plus lisible et lenregistre dans fcihier SAE_json
    $dir = __DIR__ . '/SAE_json';

    $filepath = $dir . '/' . $filename;

    $decoded = json_decode($json, true);
    if ($decoded !== null) {
        $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    if (file_put_contents($filepath, $json) === false) {
        throw new Exception("Impossible d'écrire dans le fichier $filepath");
    }
}

function is_but_formation(array $f){//fonction qui va vérifier si la formation et bien de type BUT psk on s'intèrrésse qu'au BUT
    if (isset($f['type_titre']) && $f['type_titre'] === 'BUT') return true;// ici on regarde le type et le titre pour vérifier ça

    if (isset($f['titre']) && stripos($f['titre'], 'BUT') === 0) return true;

    return false;
}

function hash_id($value){// Cette fct transforme un identifiant comme un nombre d'etudiant enune suite de caractères lisibles 
    $salt = 'ataraXyEstTrop'; // le salt pour la securisation
    return hash('sha256', $salt . (string)$value);
}

function anonymize_decisions_array(&$data){// c'est une fonction tres imporatnte pour la sécurité des données a chaque fois ou elle trouve un champs sensible elle transforme sa valeur en hash
    if (is_array($data)) {
        foreach ($data as $key => &$value) {

            if ($key === 'etudid' || $key === 'code_nip' || $key === 'code_ine') {
                if ($value !== null && $value !== '') {
                    $value = hash_id($value);//appel de la fct hash_id pour hasher le id
                }
                continue;
            }

            if (is_array($value)) {
                anonymize_decisions_array($value);
            }
        }
        unset($value); 
    }
}
/////////main////////////////

if (!$pdo) {
    die("La connexion à la BDD a échoué. Arrêt du script.");
}

try {
    /* 1) Récupération et Stockage des Formations */
    $formationsJson = scodoc_api_get('/formations');
    $formations = json_decode($formationsJson, true);

    $stmtForm = $pdo->prepare("INSERT IGNORE INTO formations (id, titre, type_titre) VALUES (?, ?, ?)");
    
    $butFormationIds = [];

    foreach ($formations as $f) {
        $fId = $f['formation_id'] ?? $f['id'] ?? null;
        if ($fId === null) continue;

        // On insère en BDD
        $stmtForm->execute([$fId, $f['titre'] ?? 'Sans titre', $f['type_titre'] ?? 'N/A']);

        // On filtre pour les BUT pour la suite
        if ((isset($f['type_titre']) && $f['type_titre'] === 'BUT') || 
            (isset($f['titre']) && stripos($f['titre'], 'BUT') === 0)) {
            $butFormationIds[] = $fId;
        }
    }
    echo "Formations synchronisées en BDD.\n";

    /* 2) Récupération et Anonymisation des Décisions de Jury */
    $stmtJury = $pdo->prepare("INSERT INTO decisions_jury (etudid_hash, formsemestre_id, annee, decision) VALUES (?, ?, ?, ?)");

    foreach([2021, 2022, 2023, 2024] as $annee){
        $fsJson = scodoc_api_get('/formsemestres/query?annee_scolaire=' . $annee);
        $formsemestres = json_decode($fsJson, true);

        foreach ($formsemestres as $fs) {
            $formationId = $fs['formation_id'] ?? ($fs['formation']['formation_id'] ?? null);
            $fsId = $fs['id'] ?? $fs['formsemestre_id'] ?? null;

            if ($fsId && in_array($formationId, $butFormationIds)) {
                $decisionsJson = scodoc_api_get("/formsemestre/$fsId/decisions_jury");
                $decisions = json_decode($decisionsJson, true);

                if (is_array($decisions)) {
                    foreach ($decisions as $d) {
                        // ANONYMISATION immédiate avant insertion
                        $etudIdRaw = $d['etudid'] ?? $d['code_nip'] ?? null;
                        if (!$etudIdRaw) continue;
                        
                        $etudIdHash = hash_id($etudIdRaw);
                        
                        // Stockage de la décision 
                        $decisionData = json_encode($d, JSON_UNESCAPED_UNICODE);
                        
                        $stmtJury->execute([$etudIdHash, $fsId, $annee, $decisionData]);
                    }
                }
            }
        }
        echo "Année $annee traitée et sécurisée en BDD.\n";
    }

} catch (Exception $e) {
    echo "Erreur lors du traitement : " . $e->getMessage();
}
?>
