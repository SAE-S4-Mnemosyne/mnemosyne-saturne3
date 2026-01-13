<?php
// Configuration pour AlwaysData
define('DB_HOST', 'mysql-mnemosyne-projet.alwaysdata.net');
define('DB_NAME', 'mnemosyne-projet_bdd');
define('DB_USER', 'mnemosyne-projet_admin');
define('DB_PASS', 'Leprojetdelasae123!');

$pdo = null;

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // En production, on évite d'afficher l'erreur brute aux utilisateurs
    // Mais pour le debug, on l'affiche si besoin, ou on loggue.
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
