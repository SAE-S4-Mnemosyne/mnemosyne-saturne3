<?php
/**
 * API SCODOC — Configuration + Auth + Requêtes
 * À inclure dans sync_data.php, test.php, consultation_data.php, etc.
 */

/* -----------------------------------------------------
   1) CONFIGURATION API (à remplir par le client à la fin)
   ----------------------------------------------------- */

function scodoc_Config() {
    return [
        // URL de base de l’API Scodoc 9 (format obligatoire)
        'urlAPI' => 'https://VOTRE-SCODOC/api',

        // Credentials API (seront fournis par le client)
        'username' => '',
        'password' => ''
    ];
}

/* -----------------------------------------------------
   2) LOGIN : récupérer un token JWT selon la doc officielle
   ----------------------------------------------------- */

function scodoc_recupToken() {
    $C = scodoc_Config();

    if (empty($C['username']) || empty($C['password'])) {
        throw new Exception("Erreur : identifiants API absents. Le client doit fournir login + mot de passe.");
    }

    $url = rtrim($C['urlAPI'], '/') . '/login';

    $payload = [
        'username' => $C['username'],
        'password' => $C['password']
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception("cURL error : " . curl_error($ch));
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (!isset($decoded['token'])) {
        throw new Exception("Échec login API Scodoc : " . $response);
    }

    return $decoded['token'];
}

/* -----------------------------------------------------
   3) Appels API GET génériques (utilisé partout dans ton code)
   ----------------------------------------------------- */

function scodoc_api_get(string $path) {
    $token = scodoc_recupToken();
    $C = scodoc_Config();

    $url = rtrim($C['urlAPI'], '/') . $path;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception("Erreur cURL GET $url : " . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("Erreur API GET ($httpCode) sur : $url");
    }

    return $response;
}
?>
