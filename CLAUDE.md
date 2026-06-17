# CLAUDE.md

Guide pour assistants IA (Claude) travaillant sur ce dépôt. Lis ce fichier avant toute modification.

## Vue d'ensemble

**Loge-Moi** est une plateforme immobilière en **PHP procédural + MySQL (PDO)**, servie par Apache via **XAMPP**. Pas de framework, pas de Composer, pas de build : chaque fichier `.php` est une page accessible directement par son URL. Le projet est un « fil rouge » (projet d'apprentissage). L'interface est **en français**.

## Lancer le projet

- Apache + MySQL via XAMPP. URL racine : `http://localhost/fil-rouge/`.
- Base de données : `ymmo_db`. Importer `sql/database.sql` puis `sql/migrations.sql` (et `sql/seed_demo.sql` pour des données de démo + un agent de test `demo.agent@logemoi.test` / `demo1234`).
- Connexion DB : identifiants dans `src/Database/Connection.php` ; `config/database.php` expose `$pdo`.
- Module Python d'analyse : `cd analytics && pip install -r requirements.txt`. Test rapide : `python analyze.py --self-test`.
- Pas de tests automatisés PHP, pas de linter, pas de pipeline. La vérification PHP se fait à la main dans le navigateur ; le pipeline Python a un mode `--self-test`.

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
- `config/database.php` est désormais un simple bootstrap : il charge l'autoloader (`src/autoload.php`) puis expose **`$pdo`** via `App\Database\Connection::getInstance()` (Singleton). Une classe `Database` légère est conservée pour compatibilité héritée.
- Toujours utiliser **`$pdo`** (PDO en mode `ERRMODE_EXCEPTION`, fetch `ASSOC` par défaut, `EMULATE_PREPARES = false`).
- Utiliser des **requêtes préparées** avec paramètres liés. Voir `login.php`, `admin/approve.php` pour le style attendu.
- ⚠️ Ne jamais interpoler de variable dans le SQL. L'ancienne injection d'`agent/index.php` a été corrigée via `AgencyRepository` / `PropertyRepository`.

### Couche POO (`src/`, namespace `App\`)
Couche orientée objet pensée SOLID / DRY / KISS, chargée par l'autoloader PSR-4 maison (`src/autoload.php`). Pas de Composer.
- **`App\Database\Connection`** — PDO Singleton ; seul endroit où vivent les identifiants DB.
- **`App\Entity\*`** — `Property`, `User`, `Agency`, `Appointment`. Entités du domaine avec fabrique `fromArray()` et petites règles métier (`statusLabel()`, `formattedPrice()`, `pricePerSquareMeter()`…). Propriétés en **camelCase** (ex. `$property->mainImage`), contrairement aux colonnes SQL en snake_case.
- **`App\Repository\*`** — accès aux données. PDO injecté au constructeur, requêtes **préparées** uniquement, tout le SQL d'une entité y est centralisé. Implémentent `RepositoryInterface`.
- **Convention pour une nouvelle page** : `require 'config/database.php';` puis `use App\Repository\XxxRepository;` et `new XxxRepository($pdo)`. Si une requête manque dans un repository, l'**ajouter au repository** plutôt que d'écrire du SQL dans la page.
- Pages déjà migrées (modèles à suivre) : `properties.php`, `agent/index.php`.

### Module d'analyse Python (`analytics/`, espace agent uniquement)
- `agent/analytics.php` (garde-fou rôle `agent` + agence requise) lance `analytics/analyze.py` via `shell_exec` en passant `--agency-id`. Le script écrit `analytics/output/report.json` + des PNG, que la page lit et affiche.
- `analyze.py` : extraction MySQL (PyMySQL), nettoyage pandas, KPIs, ventes/mois, prévisions (régression linéaire numpy), zones (prix/m² par ville), biens populaires ; graphiques matplotlib (backend Agg, thème sombre).
- Fonctions du pipeline conçues pour accepter des DataFrames → testables via `python analyze.py --self-test` (données synthétiques, sans base).
- Identifiants DB du script dans `analytics/config.json` (séparés de la config PHP). Binaire Python configurable en tête de `agent/analytics.php`.
- En cas d'évolution du schéma, penser à mettre à jour les requêtes SQL de `fetch_dataframes()` et les colonnes attendues par le nettoyage.

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
