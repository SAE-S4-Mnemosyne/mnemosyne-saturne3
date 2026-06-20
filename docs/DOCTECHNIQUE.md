# 📘 Documentation Technique - Projet Mnémosyne (Version S4)

## 1. Architecture Générale
Le projet **Mnémosyne** est une application web respectant strictement le patron de conception **MVC (Modèle-Vue-Contrôleur)**.
Il est conçu sans framework lourd (Vanilla JS / Native PHP) pour assurer une **maintenance pérenne** et une **compatibilité maximale** avec les hébergements standards et Docker.

### Technologies
-   **Frontend** : HTML5, CSS3 Modulaire, JavaScript (ES6+).
    -   *Librairies* : Google Charts (Sankey Diagram), jsPDF & html2canvas (Export PDF).
-   **Backend** : PHP 8.1+ (Architecture MVC).
-   **Base de Données** : MySQL / MariaDB via `PDO`.
-   **Sécurité** : Hachage `Argon2id`/`bcrypt`, Échappement XSS, Requêtes préparées SQL, Jetons anti-CSRF.

### Structure des Dossiers (MVC)
```
/
├── app/
│   ├── api/                # Endpoints AJAX (Sankey, Export, Bilan)
│   ├── controllers/        # Contrôleurs (AdminController, AuthController)
│   ├── core/               # Cœur (Database Singleton, CSRF)
│   ├── models/             # Modèles BDD (AdminModel, FormationModel)
│   └── views/              # Vues PHP (admin, consult)
├── css/                    # Styles CSS modulaires
├── docs/                   # Documentation technique et rapports
├── import/                 # Scripts de synchronisation ScoDoc
├── uploads/SAE_json/       # Dossier contenant les archives JSON brutes
├── env.php                 # Configuration BDD (exclu de Git via env.example.php)
├── index.php               # Routeur public
├── admin.php               # Routeur administration
├── login.php               # Routeur authentification
└── script.js               # Logique Frontend
```

---

## 2. Base de Données
Le schéma relationnel s'articule autour de l'étudiant et intègre le calcul de la dette et des compétences.

### Tables Principales
-   **`etudiant`** : Table maître (NIP, INE anonymisés).
-   **`semestre_instance`** : Inscriptions administratives (Semestres).
-   **`resultat_competence`** : Résultats académiques par unité d'enseignement.
-   **`DECISION_ANNUELLE`** : Table optimisée pour centraliser les passages, redoublements et dettes par étudiant.
-   **`admin`** : Gestion des comptes sécurisés pour l'accès backend.
-   **`mapping_codes`** & **`scenario_correspondance`** : Personnalisation de l'affichage.

---

## 3. Logique Métier : Diagrammes et Bilans (`app/api/`)

### L'Algorithme de Flux (Sankey)
1.  **Récupération** : Extraction des cohortes selon la formation et l'année.
2.  **Tracking & Dette** : Croisement avec `DECISION_ANNUELLE` pour identifier le parcours exact.
3.  **Catégorisation** :
    -   **Passage OK** : Validation classique.
    -   **Passage avec Dette** : Passage autorisé par le jury malgré des UE non validées.
    -   **Redoublement** : Réinscription niveau identique.
    -   **Sortie (NAR)** : Abandon ou réorientation.

### Les Exports (PDF & JSON)
- **PDF** : Généré côté client via `html2canvas` (capture du SVG) et `jsPDF` (mise en page pro).
- **JSON** : Généré via un endpoint PHP pour extraire la data brute de l'application.

---

## 4. Guide de Maintenance & Évolution

### Ajouter de nouvelles données (Synchronisation)
1.  Déposer les fichiers `JSON` ScoDoc bruts dans `uploads/SAE_json/`.
2.  Se connecter en administrateur et cliquer sur le bouton de synchronisation (Le système traite et normalise automatiquement les noms de formation).

### Déployer sur un nouveau serveur
1.  Copier les fichiers via FTP/SFTP ou cloner le Git.
2.  Dupliquer `env.example.php` en `env.php` et renseigner les logs BDD.
3.  Importer le fichier `mnemosyne-projet_bdd.sql` via phpMyAdmin ou CLI.

---

## 5. Sécurité
-   **Environnement** : Identifiants isolés dans `env.php` (Ignoré par Git).
-   **Authentification** : Gestion par session PHP avec régénération d'ID.
-   **CSRF** : Chaque formulaire d'administration requiert un jeton unique généré par `app/core/CSRF.php`.
-   **XSS / SQLi** : Échappement systématique en sortie (`htmlspecialchars`) et requêtes `PDO` préparées en entrée.
