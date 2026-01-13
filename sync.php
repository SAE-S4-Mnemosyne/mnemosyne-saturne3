<?php
ini_set("max_execution_time", 300); // 5 min

// -----------------------------
// 🔌 CONNEXION A MYSQL (MAMP)
// -----------------------------
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sae_sankey;charset=utf8",
        "root",
        "root"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("❌ Erreur connexion SQL : " . $e->getMessage());
}

echo "<h2>Synchronisation en cours…</h2>";

// -----------------------------
// 📂 DOSSIER DES JSON
// -----------------------------
$folder = __DIR__ . "/uploads/saejson/";
if (!is_dir($folder)) {
    die("❌ Dossier JSON introuvable : $folder");
}

$files = glob($folder . "*.json");
if (!$files) {
    die("❌ Aucun fichier JSON trouvé.");
}

// -----------------------------
// 📌 PREPARATION REQUETES
// -----------------------------
$sqlInsertEtudiant = $pdo->prepare("
    INSERT IGNORE INTO etudiant (code_nip, code_ine, etud_scodoc)
    VALUES (:nip, :ine, :etud)
");

$sqlInsertSemestre = $pdo->prepare("
    INSERT IGNORE INTO semestre_instance
    (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite)
    VALUES (:idfs, :idf, :annee, :num, :modalite)
");

$sqlInsertInscription = $pdo->prepare("
    INSERT INTO inscription
    (code_nip, id_formsemestre, decision_jury, decision_annee, etat_inscription, pct_competences, is_apc, date_maj)
    VALUES (:nip, :fs, :jury, :annee, :etat, :pct, :isapc, :maj)
");

$sqlInsertCompetence = $pdo->prepare("
    INSERT INTO resultat_competence (id_inscription, numero_competence, code_decision, moyenne)
    VALUES (:insc, :num, :code, :moy)
");

// -----------------------------
// 🔄 TRAITEMENT DES FICHIERS
// -----------------------------
foreach ($files as $file) {

    echo "<p>Lecture : <b>" . basename($file) . "</b></p>";

    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        echo "<p style='color:red'>❌ JSON invalide : $file</p>";
        continue;
    }

    // On récupère l'id_formsemestre depuis le nom du fichier
    preg_match('/fs_(\d+)/', basename($file), $m);
    $id_formsemestre = $m[1] ?? null;

    if (!$id_formsemestre) {
        echo "<p style='color:red'>❌ impossible de trouver id_formsemestre dans : $file</p>";
        continue;
    }

    // Création entrée semestre_instance (valeurs minimales)
    $sqlInsertSemestre->execute([
        ":idfs" => $id_formsemestre,
        ":idf"  => null,          // pas dans le JSON
        ":annee" => $data[0]["annee"]["annee_scolaire"] ?? null,
        ":num" => $data[0]["semestre"]["ordre"] ?? null,
        ":modalite" => null
    ]);

    // -----------------------------
    // 👇 BOUCLE SUR LES ÉTUDIANTS
    // -----------------------------
    foreach ($data as $etu) {

        // 1) Étudiant
        $sqlInsertEtudiant->execute([
            ":nip"  => $etu["code_nip"],
            ":ine"  => $etu["code_ine"],
            ":etud" => $etu["etudid"],
        ]);

        // 2) Inscription
        $sqlInsertInscription->execute([
            ":nip"  => $etu["code_nip"],
            ":fs"   => $id_formsemestre,
            ":jury" => $etu["etat"],
            ":annee"=> $etu["annee"]["ordre"] ?? null,
            ":etat" => $etu["etat"],
            ":pct"  => $etu["nb_competences"],
            ":isapc"=> $etu["is_apc"] ? 1 : 0,
            ":maj"  => date("Y-m-d H:i:s"),
        ]);

        // ID de l'inscription créée
        $id_inscription = $pdo->lastInsertId();

        // 3) Résultats de compétences (si présents)
        if (!empty($etu["rcues"])) {
            $num = 1;
            foreach ($etu["rcues"] as $rc) {
                $sqlInsertCompetence->execute([
                    ":insc" => $id_inscription,
                    ":num"  => $num,
                    ":code" => $rc["code"],
                    ":moy"  => $rc["moy"]
                ]);
                $num++;
            }
        }
    }
}

echo "<h3>✔ Synchronisation terminée !</h3>";
?>

