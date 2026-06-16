<?php
/**
 * AdminModel -- Requetes SQL liees a l'administration.
 * Gere les mappings, les scenarios et le compte administrateur.
 */
require_once __DIR__ . '/../core/Database.php';

class AdminModel {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    // --- Mappings ---

    /**
     * Recuperer tous les mappings tries par code.
     */
    public function getMappings() {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, code_scodoc, libelle_graphique FROM mapping_codes ORDER BY code_scodoc"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Ajouter ou mettre a jour un mapping.
     */
    public function ajouterMapping($code, $label) {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS mapping_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code_scodoc VARCHAR(100) UNIQUE,
            libelle_graphique VARCHAR(255)
        )");
        $stmt = $this->pdo->prepare(
            "INSERT INTO mapping_codes (code_scodoc, libelle_graphique) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE libelle_graphique = VALUES(libelle_graphique)"
        );
        $stmt->execute([$code, $label]);
    }

    /**
     * Supprimer un mapping par son identifiant.
     */
    public function supprimerMapping($id) {
        $stmt = $this->pdo->prepare("DELETE FROM mapping_codes WHERE id = ?");
        $stmt->execute([$id]);
    }

    // --- Scenarios ---

    /**
     * Recuperer tous les scenarios avec les noms des formations.
     */
    public function getScenarios() {
        try {
            $stmt = $this->pdo->query("
                SELECT sc.id_scenario, sc.type_flux,
                       fs.titre AS formation_source, fc.titre AS formation_cible
                FROM scenario_correspondance sc
                JOIN Formation fs ON sc.id_formation_source = fs.id_formation
                JOIN Formation fc ON sc.id_formation_cible = fc.id_formation
                ORDER BY fs.titre
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Ajouter un scenario de correspondance entre deux formations.
     */
    public function ajouterScenario($idSource, $idCible, $typeFlux) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO scenario_correspondance (id_formation_source, id_formation_cible, type_flux) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE type_flux = VALUES(type_flux)"
        );
        $stmt->execute([$idSource, $idCible, $typeFlux]);
    }

    /**
     * Supprimer un scenario par son identifiant.
     */
    public function supprimerScenario($id) {
        $stmt = $this->pdo->prepare("DELETE FROM scenario_correspondance WHERE id_scenario = ?");
        $stmt->execute([$id]);
    }

    // --- Compte administrateur ---

    /**
     * Recuperer le hash du mot de passe d'un administrateur.
     */
    public function getMotDePasseAdmin($adminId) {
        $stmt = $this->pdo->prepare("SELECT mot_de_passe FROM admin WHERE id_utilisateur = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ? $admin['mot_de_passe'] : null;
    }

    /**
     * Mettre a jour le mot de passe d'un administrateur.
     */
    public function mettreAJourMotDePasse($adminId, $hash) {
        $stmt = $this->pdo->prepare("UPDATE admin SET mot_de_passe = ? WHERE id_utilisateur = ?");
        $stmt->execute([$hash, $adminId]);
    }

    /**
     * Verifier les identifiants de connexion.
     * Retourne les donnees de l'admin si trouvees, null sinon.
     */
    public function verifierIdentifiants($identifiant) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin WHERE identifiant = ?");
        $stmt->execute([$identifiant]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
