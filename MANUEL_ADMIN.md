# Manuel Administrateur - Mnémosyne

Guide d'utilisation de l'interface d'administration.

## 🔑 Connexion

1. Accéder à `/login.html`
2. Entrer identifiant et mot de passe
3. Vous êtes redirigé vers `/admin.php`

## 🔄 Synchronisation des Données

### Prérequis
Placer les fichiers JSON ScoDoc dans le dossier `uploads/SAE_json/`

### Procédure
1. Cliquer sur le bouton **"Synchroniser"** dans l'en-tête
2. Attendre le message de confirmation
3. Les données sont importées dans la base

> Les données existantes ne sont pas dupliquées (insertion intelligente).

---

## 🏷️ Mapping des Codes

Le mapping permet de renommer les libellés affichés dans le Sankey.

### Ajouter un mapping
1. Section **"Mapping des Codes ScoDoc"**
2. Entrer le **Code ScoDoc** (ex: `B1-INFO-FI`)
3. Entrer le **Libellé Affiché** (ex: `BUT1 Informatique`)
4. Cliquer **"+ Ajouter"**

### Effet
Le libellé personnalisé apparaîtra dans le diagramme Sankey à la place du code technique.

### Supprimer un mapping
Cliquer sur le bouton **✕** rouge dans la liste des mappings existants.

---

## 🔀 Règles de Scénarios

Les scénarios définissent comment classifier les transitions entre formations.

### Ajouter un scénario
1. Section **"Règles de Scénarios"**
2. Sélectionner la **Formation Source** (ex: `BUT SD`)
3. Sélectionner la **Formation Cible** (ex: `BUT Informatique`)
4. Choisir le **Type de Flux** :
   - `passage` : Transition normale
   - `redoublement` : Redoublement
   - `passerelle` : Passerelle entre formations
   - `reorientation` : Réorientation
   - `abandon` : Abandon de formation
5. Cliquer **"+ Ajouter"**

### Exemple d'utilisation
Un étudiant passant de "BUT1 SD" à "BUT2 Passerelle INFO" peut être classifié comme "Passerelle" pour apparaître correctement dans le Sankey.

### Supprimer un scénario
Cliquer sur le bouton **✕** rouge dans la liste des scénarios existants.

---

## 📊 Consultation du Sankey

La page admin permet aussi de consulter le Sankey :
1. Sélectionner une **Formation** et une **Année**
2. Cliquer **"Voir les parcours"**
3. Le diagramme affiche les flux étudiants
4. Cliquer sur un flux pour voir la liste des étudiants

---

## ⚠️ Résolution de Problèmes

| Problème | Solution |
|----------|----------|
| Synchronisation échoue | Vérifier les fichiers JSON dans `uploads/SAE_json/` |
| Mapping n'apparaît pas | Vérifier que le code correspond exactement |
| Données incorrectes | Relancer une synchronisation |

---

## 🔒 Déconnexion

Cliquer sur **"Déconnexion"** dans l'en-tête pour terminer la session.
