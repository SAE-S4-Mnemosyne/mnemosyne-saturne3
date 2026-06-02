<?php
/**
 * AdminController -- Logique metier de la page admin.
 * Gere la synchronisation, les mappings et les scenarios.
 */
session_start();
require_once __DIR__ . '/../core/Database.php';

class AdminController {
    private $pdo;
    private $message = '';
    private $messageType = '';

    public function __construct() {
        // Verifier l'authentification
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.html');
            exit;
        }

        // Deconnexion
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: index.php');
            exit;
        }

        $this->pdo = Database::getInstance();

        // Recuperer le message de la session (POST-Redirect-GET)
        if (isset($_SESSION['sync_message'])) {
            $this->message = $_SESSION['sync_message'];
            $this->messageType = $_SESSION['sync_type'] ?? 'success';
            unset($_SESSION['sync_message']);
            unset($_SESSION['sync_type']);
        }
    }

    /**
     * Point d'entree principal : traite les actions POST puis affiche la vue.
     */
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verification CSRF
            if (!$this->verifyCSRF()) {
                $this->message = "Token de securite invalide.";
                $this->messageType = "error";
            } else {
                $this->processPost();
            }
        }

        // Charger les donnees pour la vue
        $data = $this->getViewData();
        $data['message'] = $this->message;
        $data['messageType'] = $this->messageType;

        // Afficher la vue
        extract($data);
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    /**
     * Traiter les actions POST.
     */
    private function processPost() {
        if (isset($_POST['add_mapping'])) {
            $this->addMapping();
        } elseif (isset($_POST['add_scenario'])) {
            $this->addScenario();
        } elseif (isset($_POST['delete_mapping'])) {
            $this->deleteMapping();
        } elseif (isset($_POST['delete_scenario'])) {
            $this->deleteScenario();
        } elseif (isset($_POST['run_sync'])) {
            $this->runSync();
        }
    }

    /**
     * Genere un token CSRF et le stocke en session.
     */
    public static function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifie le token CSRF envoye.
     */
    private function verifyCSRF() {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }

    private function addMapping() {
        $code = trim($_POST['mapping_code'] ?? '');
        $label = trim($_POST['mapping_label'] ?? '');
        if ($code && $label) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS mapping_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code_scodoc VARCHAR(100) UNIQUE,
                    libelle_graphique VARCHAR(255)
                )");
                $stmt = $this->pdo->prepare("INSERT INTO mapping_codes (code_scodoc, libelle_graphique) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE libelle_graphique = VALUES(libelle_graphique)");
                $stmt->execute([$code, $label]);
                $this->message = "Mapping ajoute : $code -> $label";
                $this->messageType = "success";
            } catch (Exception $e) {
                error_log("Erreur ajout mapping : " . $e->getMessage());
                $this->message = "Erreur lors de l'ajout du mapping.";
                $this->messageType = "error";
            }
        }
    }

    private function addScenario() {
        $source = trim($_POST['scenario_source'] ?? '');
        $target = trim($_POST['scenario_target'] ?? '');
        $type = trim($_POST['scenario_type'] ?? '');
        if ($source && $target && $type) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS scenario_correspondance (
                    id_scenario INT AUTO_INCREMENT PRIMARY KEY,
                    formation_source VARCHAR(255),
                    formation_cible VARCHAR(255),
                    type_flux VARCHAR(50),
                    UNIQUE KEY unique_scenario (formation_source, formation_cible)
                )");
                $stmt = $this->pdo->prepare("INSERT INTO scenario_correspondance (formation_source, formation_cible, type_flux) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE type_flux = VALUES(type_flux)");
                $stmt->execute([$source, $target, $type]);
                $this->message = "Scenario ajoute : $source -> $target [$type]";
                $this->messageType = "success";
            } catch (Exception $e) {
                error_log("Erreur ajout scenario : " . $e->getMessage());
                $this->message = "Erreur lors de l'ajout du scenario.";
                $this->messageType = "error";
            }
        }
    }

    private function deleteMapping() {
        $id = (int)($_POST['delete_mapping_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM mapping_codes WHERE id = ?");
                $stmt->execute([$id]);
                $this->message = "Mapping supprime.";
                $this->messageType = "success";
            } catch (Exception $e) {
                error_log("Erreur suppression mapping : " . $e->getMessage());
                $this->message = "Erreur lors de la suppression.";
                $this->messageType = "error";
            }
        }
    }

    private function deleteScenario() {
        $id = (int)($_POST['delete_scenario_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM scenario_correspondance WHERE id_scenario = ?");
                $stmt->execute([$id]);
                $this->message = "Scenario supprime.";
                $this->messageType = "success";
            } catch (Exception $e) {
                error_log("Erreur suppression scenario : " . $e->getMessage());
                $this->message = "Erreur lors de la suppression.";
                $this->messageType = "error";
            }
        }
    }

    private function runSync() {
        ini_set("max_execution_time", 300);
        try {
            // Dezip automatique
            $zipFile = 'SAE_json.zip';
            $targetDir = __DIR__ . '/../../uploads/saejson/';
            if (file_exists($zipFile)) {
                $zip = new ZipArchive;
                if ($zip->open($zipFile) === TRUE) {
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    $zip->extractTo($targetDir);
                    $zip->close();
                } else {
                    $_SESSION['sync_message'] = "Erreur technique (Zip corrompu).";
                    $_SESSION['sync_type'] = "error";
                    header('Location: admin.php');
                    exit;
                }
            }

            // Determiner le dossier JSON
            $folder = __DIR__ . "/../../uploads/SAE_json/";
            if (!is_dir($folder)) {
                $folder = __DIR__ . "/../../uploads/saejson/";
                if (is_dir($folder . "SAE_json")) $folder = $folder . "SAE_json/";
            }
            if (!is_dir($folder)) {
                $folder = __DIR__ . "/../../import/SAE_json/";
            }
            if (!is_dir($folder)) {
                $folder = __DIR__ . "/../../SAE_json/";
            }
            if (!is_dir($folder)) {
                $_SESSION['sync_message'] = "Dossier de donnees introuvable.";
                $_SESSION['sync_type'] = "error";
                header('Location: admin.php');
                exit;
            }

            require_once __DIR__ . '/../../import/run_all_imports.php';
            $results = runAllImports($this->pdo, $folder);

            if ($results['success']) {
                $stepsMsg = implode(" | ", $results['steps']);
                $_SESSION['sync_message'] = "Donnees ScoDoc synchronisees avec succes.<br><small>$stepsMsg</small>";
                $_SESSION['sync_type'] = "success";
            } else {
                $errorsMsg = implode("<br>", $results['errors']);
                $_SESSION['sync_message'] = "Synchronisation partielle ou echouee.<br>$errorsMsg";
                $_SESSION['sync_type'] = "error";
            }
        } catch (Exception $e) {
            $_SESSION['sync_message'] = "Erreur technique lors de la synchronisation.";
            $_SESSION['sync_type'] = "error";
            error_log("Erreur sync : " . $e->getMessage());
        }

        header('Location: admin.php');
        exit;
    }

    /**
     * Recuperer les donnees pour la vue.
     */
    private function getViewData() {
        $mappings = [];
        $scenarios = [];

        try {
            $stmt = $this->pdo->query("SELECT id, code_scodoc, libelle_graphique FROM mapping_codes ORDER BY code_scodoc");
            $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table n'existe pas encore
        }

        try {
            $stmt = $this->pdo->query("SELECT id_scenario, formation_source, formation_cible, type_flux FROM scenario_correspondance ORDER BY formation_source");
            $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table n'existe pas encore
        }

        return [
            'mappings' => $mappings,
            'scenarios' => $scenarios,
            'csrfToken' => self::generateCSRF()
        ];
    }
}
