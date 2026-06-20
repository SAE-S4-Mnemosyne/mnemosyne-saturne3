# Manuel Administrateur - Mnémosyne (S4)

Guide d'utilisation de l'interface d'administration.

## Connexion

1. Accéder à `/login.php`
2. Entrer identifiant et mot de passe sécurisé
3. Vous êtes redirigé vers le tableau de bord `/admin.php`

## Synchronisation des Données (Bulk Insert)

### Prérequis
Placer les fichiers d'archives JSON ScoDoc bruts dans le dossier `uploads/SAE_json/` à la racine de l'application.

### Procédure
1. Cliquer sur le bouton **"Synchroniser"** dans l'en-tête de la page Admin.
2. Le système va parser, normaliser automatiquement les noms de formations et insérer les cohortes dans la base de données.
3. Attendre le message de confirmation (bannière verte).

> Les données existantes ne sont pas dupliquées. Les données sont fusionnées via une transaction SQL sécurisée.

---

## Mapping des Codes

Le mapping permet de renommer ou regrouper les libellés affichés dans le Sankey (ex: Fusionner deux codes ScoDoc sous un même nom visuel).

### Ajouter un mapping
1. Section **"Mapping des Codes ScoDoc"**
2. Entrer le **Code ScoDoc** (ex: `B1-INFO-FI`)
3. Entrer le **Libellé Affiché** (ex: `BUT1 Informatique`)
4. Cliquer **"+ Ajouter"**

### Supprimer un mapping
Cliquer sur le bouton **✕** rouge dans la liste des mappings existants.

---

## 🔀 Règles de Scénarios
 
 Le logiciel détecte **automatiquement** les passages classiques, les redoublements et les dettes grâce à la nouvelle structure de base de données. Cependant, vous pouvez enregistrer ici vos décisions de gestion pour gérer les parcours très particuliers (comme les passerelles inter-départements).
 
 ### Ajouter une règle
 1. Section **"Règles de Scénarios"**
 2. Sélectionner la **Formation Source** (ex: `BUT SD`)
 3. Sélectionner la **Formation Cible** (ex: `BUT Informatique`)
 4. Choisir le **Type de Flux** correspondant :
    - `passerelle`
    - `reorientation`
    - `abandon`
 5. Cliquer **"+ Ajouter"**

---

## Exportation des Données Brut (JSON)

Nouveauté : Il est désormais possible d'exporter l'intégralité des mappings et scénarios de la base de données pour un import futur ou une sauvegarde.
1. Se rendre en bas de la page d'administration.
2. Cliquer sur le bouton **"Exporter les données (JSON)"**.
3. Un fichier `mnemosyne_data.json` sera téléchargé.

---

## Consultation du Sankey & Bilan de Compétences

La page publique `/index.php` permet de consulter le Sankey et d'exporter des rapports :
1. Sélectionner une **Formation** et une **Année**.
2. Vous pouvez utiliser les **Filtres Avancés** (Alternance/Initiale) ou le filtre de réussite (Passage sans dette, avec dette, redoublement).
3. Cliquer **"Voir les parcours"**.
4. Vous pouvez générer un rapport PDF de la cohorte en cliquant sur **"Exporter en PDF"**.

---

## Déconnexion

Pour des raisons de sécurité, cliquez sur **"Déconnexion"** dans l'en-tête pour détruire votre session PHP.
