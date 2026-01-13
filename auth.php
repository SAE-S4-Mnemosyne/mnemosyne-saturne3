<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header("Location: login.html?error=empty");
        exit;
    }

    try {
        // $pdo vient de config.php désormais
        if (!isset($pdo)) {
            die("Erreur de configuration : connexion BDD manquante.");
        }

        // 1. Vérifier si la table admin (minuscule) contient des utilisateurs
        $check = $pdo->query("SELECT COUNT(*) FROM admin");
        if ($check->fetchColumn() == 0) {
            // AUTO-FIX: Création d'un admin par défaut si aucun n'existe
<<<<<<< HEAD:auth.php
            $defaultPass = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admin (identifiant, mot_de_passe) VALUES ('admin', ?)");
=======
            $defaultPass = password_hash('admin', PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare("INSERT INTO ADMIN (identifiant, mot_de_passe) VALUES ('admin', ?)");
>>>>>>> 862260ce57fb93ac105373f2822c4d42e08a0dc4:Backend/code_Mnémosyne/auth.php
            $stmt->execute([$defaultPass]);
        }

        // 2. Vérification des identifiants (Table admin minuscule)
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
        die("Erreur BDD : " . $e->getMessage());
    }
} else {
    header("Location: login.html");
}
?>
