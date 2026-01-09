<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header("Location: ../../frontend/login.html?error=empty");
        exit;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Vérifier si la table ADMIN contient des utilisateurs
        $check = $pdo->query("SELECT COUNT(*) FROM ADMIN");
        if ($check->fetchColumn() == 0) {
            // AUTO-FIX: Création d'un admin par défaut si aucun n'existe
            // Mot de passe par défaut : 'admin' (haché)
            $defaultPass = password_hash('admin', PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare("INSERT INTO ADMIN (identifiant, mot_de_passe) VALUES ('admin', ?)");
            $stmt->execute([$defaultPass]);
        }

        // 2. Vérification des identifiants
        $stmt = $pdo->prepare("SELECT * FROM ADMIN WHERE identifiant = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Connexion réussie
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id_utilisateur'];
            header("Location: ../../frontend/admin.php");
            exit;
        } else {
            // Echec
            header("Location: ../../frontend/login.html?error=invalid");
            exit;
        }

    } catch (PDOException $e) {
        die("Erreur BDD : " . $e->getMessage());
    }
} else {
    header("Location: ../../frontend/login.html");
}
