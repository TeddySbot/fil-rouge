# 🏠 Loge-Moi

Plateforme web immobilière (projet fil rouge) développée en **PHP / MySQL**. Elle permet de publier et parcourir des annonces de biens, gérer des agences et des agents, échanger des messages, prendre des rendez-vous de visite, et administrer le tout via un back-office.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Rôles utilisateurs](#rôles-utilisateurs)
- [Stack technique](#stack-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Structure du projet](#structure-du-projet)
- [Architecture POO](#architecture-poo)
- [Analyse de données (Python)](#analyse-de-données-python--agents)
- [Base de données](#base-de-données)
- [Comptes de test](#comptes-de-test)
- [À faire](#à-faire)

---

## Fonctionnalités

- **Annonces immobilières** : liste des biens avec filtres (ville, prix, type), page de détail, galerie photos, compteur de vues, biens mis en avant.
- **Comparateur** : comparaison de plusieurs biens côte à côte.
- **Favoris** : sauvegarde des biens préférés par utilisateur connecté.
- **Agences** : annuaire des agences, page de détail avec les agents et leurs biens.
- **Espace agent** : tableau de bord, création/édition/suppression de biens, création ou adhésion à une agence, agenda des rendez-vous, statistiques.
- **Messagerie** : conversations entre utilisateurs avec rafraîchissement (polling), liste des messages et détail d'une conversation.
- **Rendez-vous** : demande de visite d'un bien, gérée dans l'agenda de l'agent (statuts : en attente, confirmé, annulé, terminé).
- **Back-office admin** : approbation des candidatures d'agents, gestion des utilisateurs.
- **Authentification** : inscription, connexion, déconnexion, sessions PHP, mots de passe hachés (`password_hash`).
- **Thèmes par rôle** : l'interface change de thème CSS selon le rôle (client, agent, admin).

---

## Rôles utilisateurs

| Rôle | Description |
|------|-------------|
| `client` | Utilisateur standard : parcourt les biens, gère ses favoris, envoie des messages, demande des rendez-vous. |
| `attente` | Candidat agent en attente de validation par un admin. |
| `agent` | Gère ses biens, son agence, son agenda et ses statistiques. |
| `admin` | Approuve les agents et gère les utilisateurs via le back-office. |

Le passage de `client` à `agent` se fait via une candidature : l'utilisateur passe en statut `attente`, puis un admin l'approuve (`admin/approve.php`) ou le refuse (retour à `client`).

---

## Stack technique

- **Langage** : PHP 8+ (POO : classes, namespaces, autoload PSR-4, constructor promotion, `match`)
- **Architecture** : couche POO `src/` (Entités + Repositories) servant de couche de services pour l'accès aux données — voir [Architecture POO](#architecture-poo).
- **Base de données** : MySQL / MariaDB (accès via PDO, requêtes préparées)
- **Serveur** : Apache (via XAMPP)
- **Front-end** : HTML, CSS personnalisé responsive (`public/css/style.css`), JavaScript vanilla (polling messagerie)
- **Analyse de données** : module **Python** (pandas, numpy, matplotlib, PyMySQL) réservé aux agents — voir [Analyse de données](#analyse-de-données-python--agents).
- **Pas de framework ni de Composer** — autoloader maison.

---

## Prérequis

- [XAMPP](https://www.apachefriends.org/) (ou tout environnement Apache + PHP 8+ + MySQL/MariaDB)
- PHP **8.0 ou supérieur**
- MySQL / MariaDB
- **Python 3.9+** (uniquement pour le module d'analyse de l'espace agent)

---

## Installation

### 1. Placer le projet

Cloner ou copier le projet dans le dossier web de XAMPP :

```
C:\xampp\htdocs\fil-rouge
```

### 2. Démarrer les services

Ouvrir le **XAMPP Control Panel** et démarrer **Apache** et **MySQL**.

### 3. Créer la base de données

Ouvrir phpMyAdmin (<http://localhost/phpmyadmin>) puis importer, dans l'ordre :

1. `sql/database.sql` — crée la base `ymmo_db` et les tables principales (users, agencies, properties, etc.).
2. `sql/migrations.sql` — ajoute les tables de messagerie et de rendez-vous (conversations, messages, appointments).
3. *(facultatif)* `sql/seed_demo.sql` — jeu de données de démonstration pour remplir l'espace agent et le module d'analyse. Crée un compte agent de test : **demo.agent@logemoi.test** / mot de passe **demo1234**.

Alternative en ligne de commande :

```bash
mysql -u root < sql/database.sql
mysql -u root < sql/migrations.sql
mysql -u root < sql/seed_demo.sql   # facultatif
```

### 4. Configurer la connexion

Les identifiants sont centralisés dans `src/Database/Connection.php` (constantes de classe). Les valeurs par défaut conviennent à une installation XAMPP standard :

```php
private const HOST    = 'localhost';
private const DB_NAME = 'ymmo_db';
private const DB_USER = 'root';
private const DB_PASS = '';
```

`config/database.php` charge l'autoloader et expose `$pdo` via cette connexion. Adapter `DB_USER` / `DB_PASS` si votre MySQL est protégé par un mot de passe. Le module Python lit ses identifiants dans `analytics/config.json`.

### 5. Vérifier les permissions d'upload

Le dossier `uploads/` (et `uploads/properties/`) doit être accessible en écriture pour l'upload des images de biens et des photos de profil.

### 6. Installer le module d'analyse Python (facultatif, pour les agents)

```bash
cd analytics
pip install -r requirements.txt
```

Le dossier `analytics/output/` doit être accessible en écriture (les rapports y sont générés). Voir [Analyse de données](#analyse-de-données-python--agents).

### 7. Accéder au site

Ouvrir dans le navigateur :

```
http://localhost/fil-rouge/
```

---

## Structure du projet

```
fil-rouge/
├── index.php              # Page d'accueil (hero + statistiques)
├── login.php              # Connexion
├── register.php           # Inscription
├── logout.php             # Déconnexion
├── account.php            # Profil / mon compte
├── properties.php         # Liste des biens (avec filtres)
├── property_detail.php    # Détail d'un bien
├── compare.php            # Comparateur de biens
├── favorites.php          # Favoris de l'utilisateur
├── agencies.php           # Annuaire des agences
├── agency_detail.php      # Détail d'une agence
├── request_appointment.php# Demande de rendez-vous de visite
│
├── messages.php           # Liste des conversations
├── message_detail.php     # Détail d'une conversation
├── messages_send.php      # Envoi d'un message (endpoint)
├── messages_poll.php      # Rafraîchissement messagerie (endpoint AJAX)
│
├── admin/                 # Back-office administrateur
│   ├── index.php          #   Tableau de bord admin
│   ├── approve.php        #   Approbation des candidatures agents
│   └── users.php          #   Gestion des utilisateurs
│
├── agent/                 # Espace agent
│   ├── index.php          #   Tableau de bord agent
│   ├── properties.php     #   Mes biens
│   ├── property_create.php#   Créer un bien
│   ├── property_edit.php  #   Modifier un bien
│   ├── property_delete.php#   Supprimer un bien
│   ├── createdagence.php  #   Créer une agence
│   ├── join_agency.php    #   Rejoindre une agence
│   ├── agent_agenda.php   #   Agenda des rendez-vous
│   └── stats.php          #   Statistiques
│
├── config/
│   └── database.php       # Bootstrap : autoload + $pdo (délègue à src/)
│
├── src/                   # Couche POO (namespace App\, autoload PSR-4)
│   ├── autoload.php       #   Autoloader maison
│   ├── Database/
│   │   └── Connection.php #   Connexion PDO (Singleton)
│   ├── Entity/            #   Entités du domaine
│   │   ├── Property.php
│   │   ├── User.php
│   │   ├── Agency.php
│   │   └── Appointment.php
│   └── Repository/        #   Accès aux données (requêtes préparées)
│       ├── RepositoryInterface.php
│       ├── PropertyRepository.php
│       └── AgencyRepository.php
│
├── analytics/             # Module d'analyse Python (espace agent)
│   ├── analyze.py         #   Extraction, nettoyage, stats, prévisions, graphes
│   ├── config.json        #   Identifiants MySQL du script
│   ├── requirements.txt   #   Dépendances Python
│   └── output/            #   Rapports générés (report.json + PNG)
│
├── includes/
│   ├── header.php         # En-tête + navigation (thème par rôle)
│   └── footer.php         # Pied de page
├── public/
│   ├── css/style.css      # Feuille de styles
│   └── pictures/          # Images statiques
├── uploads/
│   └── properties/        # Images de biens uploadées
└── sql/
    ├── database.sql       # Schéma initial
    ├── migrations.sql     # Messagerie + rendez-vous
    └── seed_demo.sql      # Données de démonstration (facultatif)
```

---

## Architecture POO

Le projet dispose d'une couche orientée objet sous `src/` (namespace `App\`, autoloader PSR-4 maison — sans Composer), pensée pour respecter **SOLID**, **DRY** et **KISS** :

- **`App\Database\Connection`** — connexion PDO unique (Singleton) ; centralise la configuration et évite d'ouvrir plusieurs connexions par requête.
- **`App\Entity\*`** (`Property`, `User`, `Agency`, `Appointment`) — modélisent les entités du domaine. Chaque entité offre une fabrique `fromArray()` et de petites règles métier (libellés, prix au m², formatage) pour éviter de dupliquer cette logique dans les pages.
- **`App\Repository\*`** — couche de services d'accès aux données. Les repositories reçoivent le PDO par **injection de dépendance** (inversion de dépendance), n'exposent que des **requêtes préparées** et centralisent tout le SQL d'une entité (DRY). Ils implémentent `RepositoryInterface` (abstraction commune).

Les pages restent en PHP procédural mais délèguent désormais l'accès aux données à cette couche. Exemple :

```php
require 'config/database.php';                 // expose $pdo + autoload
use App\Repository\PropertyRepository;

$repo = new PropertyRepository($pdo);
$biens = $repo->search(['status' => 'available', 'city' => 'Lyon']); // App\Entity\Property[]
```

`config/database.php` reste rétro-compatible : `$pdo` est toujours disponible pour les pages existantes. Les pages `properties.php` et `agent/index.php` ont été migrées vers cette couche (au passage, une injection SQL d'`agent/index.php` a été corrigée en requêtes préparées).

---

## Analyse de données (Python — agents)

Un module Python autonome, **réservé aux agents**, produit des rapports analytiques à partir de la base. Il est accessible depuis l'espace agent : **Tableau de bord agent → Analyse de données** (`agent/analytics.php`).

Fonctionnement : la page PHP lance le script (`agent/analytics.php` → `shell_exec` → `analytics/analyze.py --agency-id N`). Le script se connecte à MySQL, **nettoie les données** (typage, doublons, valeurs aberrantes), calcule les indicateurs, génère des graphiques, puis écrit `analytics/output/report.json` et des PNG que la page affiche.

Ce que le module produit :

- **Rapport de ventes** : ventes finalisées et chiffre d'affaires par mois.
- **Biens populaires** : top des biens par nombre de vues et par favoris.
- **Prévisions de ventes** : projection des prochains mois par régression linéaire (numpy) + tendance.
- **Zones intéressantes** : prix moyen / médian au m² par ville, classées du moins cher au plus cher.
- **Répartition** par type de bien, et indicateurs globaux (CA, prix moyen, prix moyen/m²…).

Exécution manuelle possible (sans passer par le site) :

```bash
cd analytics
python analyze.py --agency-id 1          # analyse l'agence n°1
python analyze.py --self-test            # démonstration sur données synthétiques (sans base)
```

> Le binaire Python utilisé par la page est configurable en tête de `agent/analytics.php` (`$pythonBin = 'python';` — à passer à `py` ou `python3` selon l'installation).

---

## Base de données

Base : **`ymmo_db`**

Tables principales :

- **users** — comptes (rôles : client, attente, agent, admin), profil, photo, statut actif.
- **agencies** — agences immobilières.
- **agency_agents** — lien agents ↔ agences (un agent peut appartenir à une agence).
- **properties** — biens (titre, prix, surface, ville, type, statut, features JSON, mise en avant…).
- **property_images** — photos des biens (avec image principale).
- **transactions** — transactions (en attente / complétée / annulée).
- **favorites** — favoris utilisateur ↔ bien.
- **reviews** — avis et notes (1–5) sur les agents.
- **conversations / conversation_participants / messages** — messagerie *(migrations.sql)*.
- **appointments** — rendez-vous de visite *(migrations.sql)*.

---

## Comptes de test

Si vous avez importé `sql/seed_demo.sql`, un compte **agent** est prêt à l'emploi :

- **Email** : `demo.agent@logemoi.test` · **Mot de passe** : `demo1234`

Il possède une agence, des biens, des ventes et des favoris — parfait pour tester l'espace agent et la page d'analyse de données.

Sinon, pour créer vos propres comptes :

1. Inscrivez-vous via `register.php` (crée un compte `client`).
2. Pour un compte **admin**, modifiez le rôle directement en base :

   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'votre@email.com';
   ```

3. Connectez-vous, puis approuvez les candidatures d'agents depuis `admin/approve.php`.

