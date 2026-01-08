//index.php

<?php
define('DB_HOST', 'localhost:3306');
define('DB_NAME', 'mnémosyne');
define('DB_USER', 'root');
define('DB_PASS', 'root');
$pdo = null; // Initialiser $pdo à null
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    //
    echo "Erreur de connexion à la BDD: " . $e->getMessage() . "\n";
    // $pdo reste null
}
?>