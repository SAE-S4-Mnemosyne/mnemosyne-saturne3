<?php
session_start();
require_once __DIR__ . '/app/core/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header("Location: login.html?error=empty");
        exit;
    }

    try {
        $pdo = Database::getInstance();

        // Vérification des identifiants (Table admin minuscule)
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE identifiant = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Connexion réussie
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id_utilisateur'];
            header("Location: admin.php");
            exit;
        } else {
            // Echec
            header("Location: login.html?error=invalid");
            exit;
        }

    } catch (PDOException $e) {
        error_log("Erreur authentification BDD : " . $e->getMessage());
        header("Location: login.html?error=tech");
        exit;
    }
} else {
    header("Location: login.html");
}
?>
