<?php
//include 'index.php';

function scodoc_api_get(string $path){
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

function scodoc_save_json(string $json, string $filename){
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

function is_but_formation(array $f){
    if (isset($f['type_titre']) && $f['type_titre'] === 'BUT') return true;

    if (isset($f['titre']) && stripos($f['titre'], 'BUT') === 0) return true;

    return false;
}

function hash_id($value){
    $salt = 'ataraXyEstTrop';
    return hash('sha256', $salt . (string)$value);
}

function anonymize_decisions_array(&$data){
    if (is_array($data)) {
        foreach ($data as $key => &$value) {

            if ($key === 'etudid' || $key === 'code_nip' || $key === 'code_ine') {
                if ($value !== null && $value !== '') {
                    $value = hash_id($value);
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


/* 1) Départements */
$json = scodoc_api_get('/departements');
scodoc_save_json($json, 'departements.json');
echo "Départements sauvegardés\n";

/* 2) Formations + référentiels de compétences des BUT */
$formationsJson = scodoc_api_get('/formations');
scodoc_save_json($formationsJson, 'formations.json');
echo "Formations sauvegardées\n";

$formations = json_decode($formationsJson, true);

$butFormationIds = []; // on garde les IDs de toutes les formations BUT

foreach ($formations as $formation) {
    if (!is_but_formation($formation)) continue;
    
    $formationIdBut = $formation['formation_id'] ?? $formation['id'] ?? null;
    if ($formationIdBut === null) continue;

    $butFormationIds[] = $formationIdBut;
    
    $path = '/formation/' . $formationIdBut . '/referentiel_competences';
    
    $json = scodoc_api_get($path);
    $decodedRef = json_decode($json, true);
    if ($decodedRef === null) continue;
    
    $suffix = 'BUT_' . $formationIdBut;
    if (!empty($formation['titre_court'])) {
        $suffix .= '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $formation['titre_court']);
    }
    
    $filename = 'referentiel_competences_' . $suffix . '.json';
    scodoc_save_json($json, $filename);
}
echo "Référentiels de compétences sauvegardés\n";

/* 3) Décisions de jury pour les formsemestres des BUT pour chaque année */

foreach([2021, 2022, 2023, 2024] as $annee){
	$formsemestresJson = scodoc_api_get('/formsemestres/query?annee_scolaire=' . $annee);
	scodoc_save_json($formsemestresJson, 'formsemestres_' . $annee . '.json');
	echo "Formsemestres $annee sauvegardés\n";

	$formsemestres = json_decode($formsemestresJson, true);

	// On parcourt les formsemestres
	foreach ($formsemestres as $fs) {
		$formationId = $fs['formation_id']
			?? ($fs['formation']['formation_id'] ?? null)
			?? null;

		if ($formationId === null) continue;

		if (!in_array($formationId, $butFormationIds, true)) continue;

		$formsemestreId = $fs['id'] ?? $fs['formsemestre_id'] ?? null;
		if ($formsemestreId === null) continue;

		$path = '/formsemestre/' . $formsemestreId . '/decisions_jury';
		$json = scodoc_api_get($path);
		
		//Anonymisation
		$decoded = json_decode($json, true);
		if ($decoded === null) continue;
		anonymize_decisions_array($decoded);
		$jsonAnonym = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

		$titre = $fs['titre'] ?? ($fs['titre_court'] ?? '');
		$safeTitre = $titre ? preg_replace('/[^A-Za-z0-9_-]+/', '_', $titre) : 'fs';

		$filename = 'decisions_jury_' . $annee . '_fs_' . $formsemestreId . '_' . $safeTitre . '.json';
		scodoc_save_json($jsonAnonym, $filename);
	}

	echo "Décisions de jury $annee des BUT sauvegardées\n";
}
?>
