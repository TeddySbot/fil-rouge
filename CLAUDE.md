# CLAUDE.md

Guide pour assistants IA (Claude) travaillant sur ce dépôt. Lis ce fichier avant toute modification.

## Vue d'ensemble

**Loge-Moi** est une plateforme immobilière en **PHP procédural + MySQL (PDO)**, servie par Apache via **XAMPP**. Pas de framework, pas de Composer, pas de build : chaque fichier `.php` est une page accessible directement par son URL. Le projet est un « fil rouge » (projet d'apprentissage). L'interface est **en français**.

## Lancer le projet

- Apache + MySQL via XAMPP. URL racine : `http://localhost/fil-rouge/`.
- Base de données : `ymmo_db`. Importer `sql/database.sql` puis `sql/migrations.sql`.
- Connexion DB : `config/database.php` (root / mot de passe vide par défaut).
- Pas de tests automatisés, pas de linter, pas de pipeline. La vérification se fait à la main dans le navigateur.

## Architecture & conventions

### Structure d'une page type
Chaque page suit ce squelette :
```php
<?php
session_start();
require 'config/database.php';      // expose $pdo (PDO)
// ... logique : traitement POST, requêtes ...
require 'includes/header.php';      // <head>, nav, ouverture <main>
?>
<!-- HTML de la page -->
<?php require 'includes/footer.php'; ?>
```

### Chemins relatifs (IMPORTANT)
- Les pages à la **racine** font `require 'config/database.php'` et `require 'includes/header.php'`.
- Les pages dans `admin/` et `agent/` font `require '../config/database.php'` et `require '../includes/header.php'`.
- `includes/header.php` calcule une variable `$base` (`''` à la racine, `'../'` dans admin/agent) pour préfixer les liens et le CSS. Réutilise `$base` pour tout lien dans le header.

### Connexion base de données
- `config/database.php` définit la classe `Database` et instancie `$db` puis `$pdo = $db->connect()`.
- Toujours utiliser **`$pdo`** (PDO en mode `ERRMODE_EXCEPTION`).
- Utiliser des **requêtes préparées** avec paramètres liés. Voir `login.php`, `admin/approve.php` pour le style attendu.
- ⚠️ Certaines parties (ex. `agent/index.php`) interpolent des variables dans le SQL (`... = $agency_id`). Ne pas reproduire ce schéma : préférer les requêtes préparées.

### Sessions & contrôle d'accès
- Auth via `$_SESSION['user_id']`, `$_SESSION['name']`, `$_SESSION['role']`.
- Garde-fou en haut des pages protégées :
  ```php
  if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
      header('Location: ../login.php'); exit;
  }
  ```
- Rôles : `client`, `attente` (candidat agent), `agent`, `admin`. Le thème CSS du `<body>` dépend du rôle (`theme-admin`, `theme-agent`, `theme-default`).

### Sécurité
- Mots de passe : `password_hash()` à l'inscription, `password_verify()` à la connexion. Ne jamais stocker en clair.
- Échapper toute sortie utilisateur avec `htmlspecialchars()` (déjà la norme dans le code).
- Vérifier le rôle ET `is_active` lors de la connexion.

### Front-end
- Un seul fichier CSS : `public/css/style.css` (volumineux, ~77 Ko). Les pages utilisent surtout des classes existantes (`btn`, `btn-secondary`, `card`, `container`, `dash-header`, `error`, `success`, `table-wrap`…) plus quelques styles inline.
- JavaScript minimal et vanilla (ex. polling de la messagerie via `messages_poll.php`). Pas de framework JS, pas de bundler.

### Uploads
- Images de biens dans `uploads/properties/`, références stockées dans `property_images`. Le dossier doit être accessible en écriture.

## Conventions de code

- PHP procédural, indentation 4 espaces, balises courtes d'écho `<?= ?>` dans le HTML.
- Textes UI **en français** (messages, labels, libellés de boutons).
- Messages d'erreur stockés dans `$error`, succès dans `$success`, puis affichés via les classes `.error` / `.success`.
- Commits courts en français (voir l'historique git : « join », « css », « agence »…).

## Tables clés (base `ymmo_db`)

`users`, `agencies`, `agency_agents`, `properties`, `property_images`, `transactions`, `favorites`, `reviews` (dans `sql/database.sql`) ; `conversations`, `conversation_participants`, `messages`, `appointments` (dans `sql/migrations.sql`). Voir `README.md` pour le détail.

## Pièges à éviter

- Ne pas oublier `session_start()` et le bon nombre de `../` selon le dossier.
- Toute nouvelle page protégée doit inclure le garde-fou de rôle.
- Si tu ajoutes une table, mets à jour `sql/migrations.sql` ET la documentation du README.
- Préférer systématiquement les requêtes PDO préparées, même si du code existant ne le fait pas.
- Réutiliser `$base` dans le header pour ne pas casser les liens entre racine et sous-dossiers.

## État connu / TODO

D'après `todolist.md` : photos de profil à corriger sur plusieurs pages ; messagerie à rendre instantanée (actuellement par polling).
