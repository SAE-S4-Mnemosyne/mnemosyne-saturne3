<?php
/**
 * ConsultController -- Logique pour la page de consultation publique.
 */
require_once __DIR__ . '/../core/Database.php';

class ConsultController {
    public function handleRequest() {
        // La page de consultation n'a pas de logique serveur complexe.
        // Le PDO est charge par les APIs (script.js appelle les endpoints).
        require __DIR__ . '/../views/consult/index.php';
    }
}
