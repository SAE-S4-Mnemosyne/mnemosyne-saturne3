<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Augmenter le temps d'exécution et la mémoire pour les gros fichiers
set_time_limit(300);
ini_set('memory_limit', '512M');

function getPDO() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

$pdo = getPDO();
$jsonDir = '../../SAE_json';

echo "<h2>Démarrage de la synchronisation...</h2>";

/**
 * 1. SYNCHRONISATION DES REFERENTIELS (Depuis formsemestres_*.json)
 * Remplit: DEPARTEMENT, FORMATION, SEMESTRE_INSTANCE
 */
$formFiles = glob($jsonDir . '/formsemestres_*.json');
foreach ($formFiles as $file) {
    echo "Lecture de " . basename($file) . "...<br>";
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data) continue;

    foreach ($data as $fs) {
        // DEPARTEMENT
        if (isset($fs['departement']) && isset($fs['departement']['id'])) {
            $deptId = $fs['departement']['id'];
            $acronyme = $fs['departement']['acronym'] ?? null;
            $nom = $fs['departement']['dept_name'] ?? null;

            // On vérifie si existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM DEPARTEMENT WHERE id_dept = ?");
            $stmt->execute([$deptId]);
            if ($stmt->fetchColumn() == 0) {
                $req = $pdo->prepare("INSERT INTO DEPARTEMENT (id_dept, acronyme, nom_complet) VALUES (?, ?, ?)");
                $req->execute([$deptId, $acronyme, $nom]);
            }
        }

        // FORMATION
        if (isset($fs['formation']) && isset($fs['formation']['id'])) {
            $formId = $fs['formation']['id'];
            $deptIdFK = $fs['formation']['dept_id'] ?? null;
            $titre = $fs['formation']['titre'] ?? null;
            $codeScoDoc = $fs['formation']['formation_code'] ?? null;

             $stmt = $pdo->prepare("SELECT COUNT(*) FROM FORMATION WHERE id_formation = ?");
             $stmt->execute([$formId]);
             if ($stmt->fetchColumn() == 0) {
                 $req = $pdo->prepare("INSERT INTO FORMATION (id_formation, id_dept, code_scodoc, titre) VALUES (?, ?, ?, ?)");
                 $req->execute([$formId, $deptIdFK, $codeScoDoc, $titre]);
             }
        }

        // SEMESTRE_INSTANCE
        $fsId = $fs['id']; // ex: 1299
        $formationIdFK = $fs['formation_id'] ?? null;
        $anneeScolaire = $fs['annee_scolaire'] ?? null;
        $modalite = $fs['modalite'] ?? null;
        // Extraction du numéro de semestre depuis le titre ou autre (pas toujours explicite en int, on tente de deviner via semestre_id qui est souvent 1, 2, 3...)
        $numSemestre = $fs['semestre_id'] ?? null;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM SEMESTRE_INSTANCE WHERE id_formsemestre = ?");
        $stmt->execute([$fsId]);
        if ($stmt->fetchColumn() == 0) {
            $req = $pdo->prepare("INSERT INTO SEMESTRE_INSTANCE (id_formsemestre, id_formation, annee_scolaire, numero_semestre, modalite) VALUES (?, ?, ?, ?, ?)");
            $req->execute([$fsId, $formationIdFK, $anneeScolaire, $numSemestre, $modalite]);
        }
    }
}
echo "Référentiels (Formations, Départements, Semestres) synchronisés.<br>";


/**
 * 2. SYNCHRONISATION DES DONNEES ETUDIANTS (Depuis decisions_jury_*.json)
 * Remplit: ETUDIANT, INSCRIPTION, RESULTAT_COMPETENCE
 */
$juryFiles = glob($jsonDir . '/decisions_jury_*.json');
$countInscriptions = 0;

foreach ($juryFiles as $file) {
    // Extraction du formsemestre_id depuis le nom du fichier (ex: ..._fs_1210_...)
    if (preg_match('/fs_(\d+)_/', basename($file), $matches)) {
        $fsId = $matches[1];
    } else {
        continue; // Pas d'ID trouvé, on ignore
    }

    $etudData = json_decode(file_get_contents($file), true);
    if (!$etudData) continue;

    foreach ($etudData as $e) {
        $nip = $e['code_nip'] ?? null;
        $scodocId = $e['etudid'] ?? null;
        $ine = $e['code_ine'] ?? null;

        if (!$nip) continue; // Pas de NIP, on ne peut pas l'identifier

        // ETUDIANT
        // On insère ou on met à jour (si on a enfin le code INE par exemple)
        $stmt = $pdo->prepare("INSERT INTO ETUDIANT (code_nip, code_ine, etudid_scodoc) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE code_ine = VALUES(code_ine), etudid_scodoc = VALUES(etudid_scodoc)");
        $stmt->execute([$nip, $ine, $scodocId]);

        // INSCRIPTION
        // Données de décision
        $decisionAnnee = $e['annee']['code'] ?? null; // ex: ADM, RED
        // Pour la décision de jury (semestre), c'est souvent la "décision" tout court, mais dans ce JSON structure on a 'annee' qui est le plus important pour le passage.
        // On va regarder s'il y a une décision globale. Le champ 'etat' vaut 'I' (Inscrit).
        // Le JSON est complexe pour les décisions de semestre vs année.
        // Pour le Sankey "Annee N vers N+1", on regarde 'decision_annee'.

        // Vérif doublon inscription
        $stmt = $pdo->prepare("SELECT id_inscription FROM INSCRIPTION WHERE code_nip = ? AND id_formsemestre = ?");
        $stmt->execute([$nip, $fsId]);
        $existingId = $stmt->fetchColumn();

        if (!$existingId) {
            $req = $pdo->prepare("INSERT INTO INSCRIPTION (code_nip, id_formsemestre, decision_annee, etat_inscription, is_apc) VALUES (?, ?, ?, ?, ?)");
            $req->execute([$nip, $fsId, $decisionAnnee, $e['etat'] ?? null, $e['is_apc'] ? 1 : 0]);
            $existingId = $pdo->lastInsertId();
            $countInscriptions++;
        } else {
            // Update éventuel
            $req = $pdo->prepare("UPDATE INSCRIPTION SET decision_annee = ? WHERE id_inscription = ?");
            $req->execute([$decisionAnnee, $existingId]);
        }
    }
}

echo "Données étudiants synchronisées. ($countInscriptions nouvelles inscriptions).<br>";
echo "<h3>Synchronisation terminée avec succès.</h3>";
