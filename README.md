# Mnémosyne - Suivi des Cohortes IUT

Application web de visualisation des parcours étudiants de l'IUT de Villetaneuse via diagrammes Sankey.

## Fonctionnalités

- **Diagramme Sankey** : Visualisation des flux étudiants BUT1 → BUT2 → BUT3 → Diplômé
- **Filtres** : Par formation (6 BUT + Tout l'IUT) et par année de promotion
- **Export PDF** : Génération d'un rapport PDF incluant une synthèse chiffrée et le contexte (formation, année). <!-- Fonctionnalité ajoutée -->
- **Interactivité** : Clic sur un flux pour voir la liste des étudiants
- **Administration** : Synchronisation, mapping des codes, scénarios de flux

## Installation

### Prérequis
- PHP 7.4+ avec extensions PDO, JSON, ZIP
- MySQL/MariaDB 5.7+
- Serveur web Apache/Nginx

### Étapes

1. **Cloner le projet**
```bash
git clone <url_depot>
cd mnemosyne
```

2. **Configurer la base de données**
```bash
# Créer le fichier config.php depuis le sample
cp config.sample.php config.php
# Éditer config.php avec vos identifiants BDD
```

3. **Importer le schéma SQL**
```sql
mysql -u <user> -p <database> < full_schema.sql
```

4. **Créer un administrateur**
```sql
INSERT INTO admin (identifiant, mot_de_passe) 
VALUES ('admin', '$2y$10$...');  -- Hash bcrypt du mot de passe
```

5. **Synchroniser les données**
   - Placer les fichiers JSON ScoDoc dans `uploads/SAE_json/`
   - Connexion admin → Bouton "Synchroniser"

## ⚙️ Configuration

Fichier `config.php` requis :
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mnemosyne');
define('DB_USER', 'user');
define('DB_PASS', 'password');
```

## Structure du Projet

```
├── api/                            # APIs JSON (Données)
│   ├── recuperer_donnees_flux.php  # Données pour le Sankey (noeuds/liens)
│   ├── recuperer_etudiants_par_flux.php # Liste détaillée des étudiants
│   ├── recuperer_options.php       # Options des filtres (Formations/Années)
│   └── utilitaires.php             # Fonctions partagées
├── import/                         # Scripts d'importation (Excel/CSV)
├── uploads/                        # Stockage des fichiers JSON ScoDoc
├── admin.php                       # Tableau de bord administration
├── auth.php                        # Gestion session/authentification
├── config.php                      # Configuration BDD
├── index.php                       # Page d'accueil (Consultation)
├── login.html                      # Page de connexion
├── mnemosyne-projet_bdd.sql        # Schéma Base de Données
├── script.js                       # Logique JS principale (Graphiques)
├── styles.css                      # Styles globaux
├── loader.js / loader.css          # Animation de chargement
└── sync.php                        # Script de synchronisation
```

## Sécurité

- Authentification par session PHP
- Mots de passe hashés (bcrypt)
- Requêtes préparées contre injections SQL
- Fichier `config.php` exclu de Git

## Auteurs

Projet SAE - BUT Informatique - Université Sorbonne Paris Nord
