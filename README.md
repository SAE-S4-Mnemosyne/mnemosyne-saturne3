# Mnémosyne - Suivi des Cohortes IUT

Application web de visualisation des parcours étudiants de l'IUT de Villetaneuse via diagrammes Sankey.

## Fonctionnalités

- **Diagramme Sankey** : Visualisation des flux étudiants BUT1 → BUT2 → BUT3 → Diplômé
- **Filtres** : Par formation (6 BUT + Tout l'IUT) et par année de promotion
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
├── api/                    # APIs JSON
│   ├── get_flow_data.php   # Données Sankey
│   ├── get_students_by_flow.php  # Liste étudiants
│   └── get_options.php     # Formations/années
├── admin.php               # Interface administration
├── index.php               # Interface consultation
├── script.js               # Logique frontend
├── styles.css              # Styles CSS
├── config.php              # Configuration BDD (non versionné)
└── full_schema.sql         # Schéma base de données
```

## Sécurité

- Authentification par session PHP
- Mots de passe hashés (bcrypt)
- Requêtes préparées contre injections SQL
- Fichier `config.php` exclu de Git

## Auteurs

Projet SAE - BUT Informatique - Université Sorbonne Paris Nord
