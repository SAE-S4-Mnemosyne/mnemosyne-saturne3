<?php
/**
 * FormationModel -- Requetes SQL liees aux formations.
 * Recupere la liste des formations disponibles dans la base.
 */
require_once __DIR__ . '/../core/Database.php';

class FormationModel {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /**
     * Recuperer toutes les formations triees par titre.
     */
    public function getAll() {
        try {
            $stmt = $this->pdo->query("SELECT id_formation, titre FROM Formation ORDER BY titre");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Recuperer une formation par son identifiant.
     */
    public function getById($idFormation) {
        $stmt = $this->pdo->prepare("SELECT id_formation, titre FROM Formation WHERE id_formation = ?");
        $stmt->execute([$idFormation]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Recuperer les formations qui ont au moins une inscription.
     */
    public function getFormationsAvecInscriptions() {
        $stmt = $this->pdo->query("
            SELECT DISTINCT f.titre
            FROM Formation f
            JOIN Semestre_Instance si ON f.id_formation = si.id_formation
            JOIN Inscription i ON si.id_formsemestre = i.id_formsemestre
            WHERE f.titre IS NOT NULL AND f.titre != ''
            ORDER BY f.titre
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
