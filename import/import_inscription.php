<?php
// import_inscription.php

// 1. Connexion à la base
require_once __DIR__ . '/connectDB_alwaysdata.php';

// 2. Dossier où se trouvent les fichiers decisions_jury_*.json
$decisionsDir = __DIR__ . '/SAE_json';

// 3. Récupérer la liste de tous les fichiers decisions_jury_*.json
$pattern = $decisionsDir . '/decisions_jury_*.json';
$files = glob($pattern);

if (empty($files)) {
    die("Aucun fichier JSON trouvé avec le pattern : $pattern\n");
}

echo "Nombre de fichiers trouvés : " . count($files) . "\n";

// 4. Préparer la requête d'INSERT pour Inscription
// Rappel de la table :
// CREATE TABLE Inscription (
//     id_inscription integer PRIMARY KEY,
//     code_nip varchar,
//     id_formsemestre integer,
//     decision_jury varchar,
//     decision_annee varchar,
//     etat_inscription varchar,
//     pct_competences double precision,
//     is_apc boolean,
//     date_maj timestamp,
//     FOREIGN KEY (code_nip) REFERENCES Etudiant(code_nip),
//     FOREIGN KEY (id_formsemestre) REFERENCES Semestre_Instance(id_formsemestre)
// );

$sql = "INSERT IGNORE INTO Inscription (
            code_nip,
            id_formsemestre,
            decision_jury,
            decision_annee,
            etat_inscription,
            pct_competences,
            is_apc,
            date_maj
        ) VALUES (
            :code_nip,
            :id_formsemestre,
            :decision_jury,
            :decision_annee,
            :etat_inscription,
            :pct_competences,
            :is_apc,
            :date_maj
        )";

$stmt = $pdo->prepare($sql);

// On fixe la date de mise à jour à maintenant pour tout l'import
$dateMaj = date('Y-m-d H:i:s');

$totalFichiers      = 0;
$totalEnregsJSON    = 0;
$totalInscriptions  = 0;

foreach ($files as $filepath) {
    $totalFichiers++;

    $filename = basename($filepath);
    echo "Traitement du fichier : $filename\n";

    // 5. Récupérer l'année et l'id_formsemestre à partir du nom du fichier
    //    Exemple : decisions_jury_2021_fs_1052_BUT_R_T.json
    if (!preg_match('/^decisions_jury_(\d{4})_fs_(\d+)_/i', $filename, $matches)) {
        echo "  -> Nom de fichier inattendu, ignoré.\n";
        continue;
    }

    $anneeFichier   = (int) $matches[1]; // 2021
    $formsemestreId = (int) $matches[2]; // 1052

    // 6. Lire le contenu du fichier JSON
    $jsonContent = file_get_contents($filepath);
    $data = json_decode($jsonContent, true);

    if ($data === null) {
        echo "  -> Erreur de décodage JSON : " . json_last_error_msg() . "\n";
        continue;
    }

    // Chaque élément de $data = un étudiant pour ce formsemestre
    foreach ($data as $etudiant) {
        $totalEnregsJSON++;

        $codeNip = $etudiant['code_nip'] ?? null;
        if (empty($codeNip)) {
            // Si pas de code_nip, on ne peut pas relier à Etudiant -> on saute
            continue;
        }

        // decision_jury : semestre.code (si présent)
        $decisionJury = null;
        if (!empty($etudiant['semestre']) && isset($etudiant['semestre']['code'])) {
            $decisionJury = $etudiant['semestre']['code'];
        }

        // decision_annee : annee.code (si présent)
        $decisionAnnee = $etudiant['annee']['code'] ?? null;

        // etat_inscription : etat
        $etatInscription = $etudiant['etat'] ?? null;

        // pct_competences : pour l'instant NULL (tu pourras le calculer plus tard)
        $pctCompetences = null;

        // is_apc : booléen -> on le convertit en 0/1
        $isApc = null;
        if (isset($etudiant['is_apc'])) {
            $isApc = $etudiant['is_apc'] ? 1 : 0;
        }

        // 7. Exécution de l'INSERT
        $stmt->execute([
            ':code_nip'        => $codeNip,
            ':id_formsemestre' => $formsemestreId,
            ':decision_jury'   => $decisionJury,
            ':decision_annee'  => $decisionAnnee,
            ':etat_inscription' => $etatInscription,
            ':pct_competences' => $pctCompetences,
            ':is_apc'          => $isApc,
            ':date_maj'        => $dateMaj,
        ]);

        $totalInscriptions++;
    }
}

echo "\nImport Inscription terminé.\n";
echo "Fichiers traités : $totalFichiers\n";
echo "Enregistrements JSON lus : $totalEnregsJSON\n";
echo "Inscriptions insérées : $totalInscriptions\n";
