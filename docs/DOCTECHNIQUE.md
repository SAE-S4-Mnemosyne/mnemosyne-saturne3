# 📘 Documentation Technique - Projet Mnémosyne

## 1. Architecture Générale
Le projet **Mnémosyne** est une application web monolithique respectant l'architecture client-serveur standard.
Il est conçu sans framework lourd (Vanilla JS / Native PHP) pour assurer une **maintenance pérenne** et une **compatibilité maximale** avec les hébergements standards (ex: AlwaysData).

### Technologies
-   **Frontend** : HTML5, CSS3, JavaScript (ES6+).
    -   *Librairie* : Google Charts (Sankey Diagram).
-   **Backend** : PHP 8.1+.
-   **Base de Données** : MySQL / MariaDB via `PDO`.
-   **Sécurité** : Hachage `password_hash`, Échappement XSS, Requêtes préparées SQL.

### Structure des Dossiers
```
/
├── admin.php           # Interface d'administration (protégée)
├── index.php           # Interface publique (Visualisation)
├── config.php          # Configuration BDD (exclu du Git)
├── styles.css          # Styles globaux (Thèmes Clair/Sombre)
├── script.js           # Logique Frontend (Sankey + Modales)
├── api/                # Endpoints AJAX
│   ├── get_flow_data.php        # Calcul des flux Sankey
│   └── get_students_by_flow.php # Détails étudiants (Modale)
└── assets/             # Images et ressources statiques
```

---

## 2. Base de Données
Le schéma relationnel est centré autour de l'étudiant et de son parcours.

### Tables Principales
-   **`etudiant`** : Table maître (NIP, INE, Nom masqué).
-   **`semestre_instance`** : Inscriptions administratives (Semestres).
-   **`resultat_competence`** : Résultats académiques.
-   **`mapping_codes`** : Table de correspondance entre codes ScoDoc (ex: `MINFO1`) et libellés lisibles (ex: `BUT1 Info`).
-   **`scenario_correspondance`** : Règles métier pour détecter les types de flux (Passerelle, Redoublement) si les règles automatiques ne suffisent pas.

---

## 3. Logique Métier : Le Diagramme Sankey (`api/get_flow_data.php`)
Le cœur du projet réside dans la génération automatique des cohortes.

### Algorithme de Flux
1.  **Récupération** : On sélectionne les étudiants d'une formation et d'une année de départ (BUT1).
2.  **Tracking** : Pour chaque étudiant, on regarde son inscription l'année N, N+1, et N+2.
3.  **Catégorisation** :
    -   **Passage** : Semestre pair validé ➔ Semestre impair niveau supérieur.
    -   **Redoublement** : Réinscription dans le même niveau.
    -   **Réorientation (NAR)** : Disparition de la base ou inscription dans une autre formation (si détectable).
    -   **Diplômé** : Validation du S6 (BUT3).

### Sécurité & Performance
-   Les requêtes SQL utilisent des **Join** optimisés.
-   Les données sensibles (Noms) ne sont **jamais** envoyées au frontend (seulement NIP/INE anonymisés).

---

## 4. Guide de Maintenance & Évolution

### Ajouter une nouvelle année/import
1.  Importer les JSON ScoDoc via le script de synchronisation (ou via l'interface Admin si implémenté).
2.  Le système détectera automatiquement les nouveaux étudiants.

### Modifier les couleurs du graphique
Modifier le fichier `script.js`, variable `colors` dans la fonction `drawSankey()`.

### Déployer sur un nouveau serveur
1.  Copier les fichiers via FTP/SFTP.
2.  Importer `full_schema.sql` dans la nouvelle base de données.
3.  Configurer `config.php` avec les nouveaux identifiants.

---

## 5. Sécurité
-   **Admin** : Accès protégé par session PHP.
-   **XSS** : Toutes les données affichées via JS sont échappées (`escapeHtml`).
-   **SQLi** : Utilisation exclusive de requêtes préparées.
