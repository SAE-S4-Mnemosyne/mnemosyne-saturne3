<?php
/**
 * Script de test pour verifier la nouvelle architecture MVC et les modifications BDD
 */

// 1. Test presence fichiers MVC
$expectedFiles = [
    '../app/core/Database.php',
    '../app/controllers/AdminController.php',
    '../app/controllers/ConsultController.php',
    '../app/views/admin/dashboard.php',
    '../app/views/consult/index.php',
    '../admin.php',
    '../index.php'
];

echo "=== TEST ARCHITECTURE MVC ===\n";
$allFilesOk = true;
foreach ($expectedFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "OK: $file\n";
    } else {
        echo "FAIL: $file manquant\n";
        $allFilesOk = false;
    }
}

// 2. Test code mort supprime
echo "\n=== TEST CODE MORT ===\n";
$deadFiles = [
    '../sync.php',
    '../import/import_departements.php'
];
foreach ($deadFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        echo "OK: $file bien supprime\n";
    } else {
        echo "FAIL: $file toujours present\n";
        $allFilesOk = false;
    }
}

// 3. Test Singleton Database
echo "\n=== TEST DATABASE ===\n";
try {
    require_once __DIR__ . '/../app/core/Database.php';
    $pdo1 = Database::getInstance();
    $pdo2 = Database::getInstance();
    
    if ($pdo1 === $pdo2) {
        echo "OK: Database est bien un singleton\n";
    } else {
        echo "FAIL: Instances PDO multiples\n";
    }
} catch (Exception $e) {
    echo "ERREUR CONNEXION: " . $e->getMessage() . "\n";
}

if ($allFilesOk) {
    echo "\n=> SUCCES : Tous les tests structurels sont passes !\n";
} else {
    echo "\n=> ECHEC : Il manque des elements ou du code mort persiste.\n";
}
