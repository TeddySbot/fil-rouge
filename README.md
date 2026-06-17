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

- **Langage** : PHP 8+ (utilise `match`, opérateurs `??`)
- **Base de données** : MySQL / MariaDB (accès via PDO)
- **Serveur** : Apache (via XAMPP)
- **Front-end** : HTML, CSS personnalisé (`public/css/style.css`), JavaScript vanilla (polling messagerie)
- **Pas de framework ni de dépendances Composer** — projet en PHP « vanilla ».

---

## Prérequis

- [XAMPP](https://www.apachefriends.org/) (ou tout environnement Apache + PHP 8+ + MySQL/MariaDB)
- PHP **8.0 ou supérieur**
- MySQL / MariaDB

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

Alternative en ligne de commande :

```bash
mysql -u root < sql/database.sql
mysql -u root < sql/migrations.sql
```

### 4. Configurer la connexion

Les identifiants se trouvent dans `config/database.php`. Les valeurs par défaut conviennent à une installation XAMPP standard :

```php
private $host    = 'localhost';
private $db_name = 'ymmo_db';
private $db_user = 'root';
private $db_pass = '';
```

Adapter `db_user` / `db_pass` si votre MySQL est protégé par un mot de passe.

### 5. Vérifier les permissions d'upload

Le dossier `uploads/` (et `uploads/properties/`) doit être accessible en écriture pour l'upload des images de biens et des photos de profil.

### 6. Accéder au site

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
│   └── database.php       # Connexion PDO (classe Database)
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
    └── migrations.sql     # Messagerie + rendez-vous
```

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

Aucun compte n'est pré-rempli dans le schéma. Pour tester :

1. Inscrivez-vous via `register.php` (crée un compte `client`).
2. Pour un compte **admin**, modifiez le rôle directement en base :

   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'votre@email.com';
   ```

3. Connectez-vous, puis approuvez les candidatures d'agents depuis `admin/approve.php`.

