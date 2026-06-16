<?php
/**
 * AdminController -- Logique metier de la page admin.
 * Gere la synchronisation, les mappings et les scenarios.
 * Delegue les requetes SQL aux Models (AdminModel, FormationModel).
 */
session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/AdminModel.php';
require_once __DIR__ . '/../models/FormationModel.php';

class AdminController {
    private $adminModel;
    private $formationModel;
    private $message = '';
    private $messageType = '';

    public function __construct() {
        // Verifier l'authentification
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }

        // Deconnexion
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: index.php');
            exit;
        }

        $this->adminModel = new AdminModel();
        $this->formationModel = new FormationModel();

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
        } elseif (isset($_POST['update_password'])) {
            $this->updatePassword();
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
                $this->adminModel->ajouterMapping($code, $label);
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
        $source = (int)($_POST['scenario_source'] ?? 0);
        $target = (int)($_POST['scenario_target'] ?? 0);
        $type = trim($_POST['scenario_type'] ?? '');
        if ($source > 0 && $target > 0 && $type) {
            try {
                $this->adminModel->ajouterScenario($source, $target, $type);
                $this->message = "Scenario ajoute avec succes.";
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
                $this->adminModel->supprimerMapping($id);
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
                $this->adminModel->supprimerScenario($id);
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
            $pdo = Database::getInstance();
            $results = runAllImports($pdo, $folder);

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
     * Modifier le mot de passe administrateur.
     * Verifie l'ancien mot de passe, valide la confirmation
     * et hache le nouveau via password_hash (Argon2id).
     */
    private function updatePassword() {
        $ancien   = $_POST['old_password']     ?? '';
        $nouveau  = $_POST['new_password']     ?? '';
        $confirme = $_POST['confirm_password'] ?? '';

        // Verifier que tous les champs sont remplis
        if (empty($ancien) || empty($nouveau) || empty($confirme)) {
            $this->message = "Tous les champs sont obligatoires.";
            $this->messageType = "error";
            return;
        }

        // Verifier que le nouveau mot de passe et la confirmation correspondent
        if ($nouveau !== $confirme) {
            $this->message = "Le nouveau mot de passe et la confirmation ne correspondent pas.";
            $this->messageType = "error";
            return;
        }

        // Verifier la longueur minimale
        if (strlen($nouveau) < 8) {
            $this->message = "Le mot de passe doit contenir au moins 8 caracteres.";
            $this->messageType = "error";
            return;
        }

        try {
            $adminId = $_SESSION['admin_id'] ?? null;
            if (!$adminId) {
                $this->message = "Session invalide, veuillez vous reconnecter.";
                $this->messageType = "error";
                return;
            }

            // Recuperer le hash actuel via le Model
            $hashActuel = $this->adminModel->getMotDePasseAdmin($adminId);

            if (!$hashActuel || !password_verify($ancien, $hashActuel)) {
                $this->message = "L'ancien mot de passe est incorrect.";
                $this->messageType = "error";
                return;
            }

            // Hacher le nouveau mot de passe et mettre a jour via le Model
            $hash = password_hash($nouveau, PASSWORD_ARGON2ID);
            $this->adminModel->mettreAJourMotDePasse($adminId, $hash);

            $this->message = "Mot de passe modifie avec succes.";
            $this->messageType = "success";

        } catch (Exception $e) {
            error_log("Erreur changement mot de passe : " . $e->getMessage());
            $this->message = "Erreur technique lors du changement de mot de passe.";
            $this->messageType = "error";
        }
    }

    /**
     * Recuperer les donnees pour la vue via les Models.
     */
    private function getViewData() {
        return [
            'mappings' => $this->adminModel->getMappings(),
            'scenarios' => $this->adminModel->getScenarios(),
            'formations' => $this->formationModel->getAll(),
            'csrfToken' => self::generateCSRF()
        ];
    }
}
