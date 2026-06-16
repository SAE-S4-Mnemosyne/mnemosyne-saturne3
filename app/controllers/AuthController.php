<?php
/**
 * AuthController -- Logique metier de l'authentification.
 * Gere la connexion et l'affichage de la page de login.
 */
session_start();
require_once __DIR__ . '/../models/AdminModel.php';

class AuthController {
    private $adminModel;

    public function __construct() {
        $this->adminModel = new AdminModel();
    }

    public function handleRequest() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!$this->verifyCSRF()) {
                header("Location: login.php?error=tech");
                exit;
            }
            $this->processLogin();
        } else {
            $this->showLogin();
        }
    }

    public static function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCSRF() {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }

    private function processLogin() {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            header("Location: login.php?error=empty");
            exit;
        }

        try {
            // Verifier via le Model (qui gere la connexion BDD)
            $user = $this->adminModel->verifierIdentifiants($username);

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Connexion reussie
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id_utilisateur'];
                header("Location: admin.php");
                exit;
            } else {
                // Echec
                header("Location: login.php?error=invalid");
                exit;
            }

        } catch (Exception $e) {
            error_log("Erreur authentification BDD : " . $e->getMessage());
            header("Location: login.php?error=tech");
            exit;
        }
    }

    private function showLogin() {
        // Rediriger vers l'admin si deja connecte
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            header("Location: admin.php");
            exit;
        }
        
        // Charger la vue de login
        $csrfToken = self::generateCSRF();
        require __DIR__ . '/../views/auth/login.php';
    }
}
