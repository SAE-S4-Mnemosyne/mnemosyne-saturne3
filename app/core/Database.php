<?php
/**
 * Classe de connexion PDO centralisee.
 * Utilise le pattern Singleton pour eviter les connexions multiples.
 */
class Database {
    private static $instance = null;

    /**
     * Retourne l'instance PDO unique.
     * Charge config.php au premier appel.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            require_once __DIR__ . '/../../config.php';
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Message generique en production
                error_log("Erreur connexion BDD : " . $e->getMessage());
                die("Erreur de connexion a la base de donnees.");
            }
        }
        return self::$instance;
    }

    // Empecher l'instanciation directe
    private function __construct() {}
    private function __clone() {}
}
?>
