# Ymmo - Plateforme Immobilière

## 📋 Description

**Ymmo** est une plateforme web moderne pour la gestion de l'achat, la vente et la location de biens immobiliers. Elle permet aux agences, aux agents immobiliers et aux clients de publier, rechercher et gérer des propriétés.

## ✨ Fonctionnalités Principales

### 1. Gestion des utilisateurs
- ✅ Système d'authentification (inscription/connexion/déconnexion)
- ✅ Gestion des rôles (Client, Agent, Administrateur)
- ✅ Profil utilisateur modifiable
- ✅ Sécurité avec hash bcrypt

### 2. Gestion des biens immobiliers
- ✅ Créer, modifier, supprimer des annonces
- ✅ Afficher une liste paginée des biens
- ✅ Détail complet de chaque bien
- ✅ Système de gestion d'images
- ✅ Multiples filtres de recherche avancée

### 3. Recherche avancée
- ✅ Filtrer par ville
- ✅ Filtrer par prix (min/max)
- ✅ Filtrer par type de bien (maison/appartement/terrain/commercial)
- ✅ Filtrer par nombre de pièces
- ✅ Pagination des résultats

### 4. Tableau de bord Agent
- ✅ Gestion des biens personnels
- ✅ Statistiques et performance
- ✅ Suivi des vues et interactions
- ✅ Gestion des transactions

### 5. Gestion des Agences
- ✅ Gestion complète des agences
- ✅ Liaison agents-agences
- ✅ Statistiques par agence
- ✅ Suivi des biens par agence

### 6. API REST Complète
- ✅ Endpoints pour toutes les opérations
- ✅ Responses en JSON
- ✅ Gestion des erreurs

---

## 🏗️ Architecture

### Structure du projet

```
ymmo/
├── config/
│   └── database.php              # Configuration PDO MySQL
│
├── controllers/
│   ├── AuthController.php        # Authentification
│   ├── PropertyController.php    # Gestion des biens
│   ├── AgencyController.php      # Gestion des agences
│   └── UserController.php        # Gestion des utilisateurs
│
├── models/
│   ├── User.php                  # Modèle Utilisateur
│   ├── Property.php              # Modèle Bien immobilier
│   ├── Agency.php                # Modèle Agence
│   └── Transaction.php           # Modèle Transaction
│
├── views/
│   ├── layout/
│   │   ├── header.php            # En-tête global
│   │   └── footer.php            # Pied de page global
│   ├── auth/
│   │   ├── login.php             # Connexion
│   │   └── register.php          # Inscription
│   ├── properties/
│   │   ├── list.php              # Liste des biens
│   │   ├── detail.php            # Détail d'un bien
│   │   ├── create.php            # Créer un bien
│   │   └── edit.php              # Modifier un bien
│   ├── dashboard/
│   │   └── agent_dashboard.php   # Tableau de bord
│   ├── index.php                 # Accueil
│   └── 404.php                   # Erreur 404
│
├── public/
│   ├── index.php                 # Point d'entrée
│   ├── css/
│   │   └── style.css             # Styles CSS
│   ├── js/
│   │   └── script.js             # Scripts JavaScript
│   └── images/properties/        # Dossier images
│
├── routes/
│   └── web.php                   # Définition des routes
│
├── sql/
│   └── database.sql              # Schéma et données
│
└── README.md                     # Documentation
```

---

## 📦 Stack Technique

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Backend | PHP | 8.0+ |
| Database | MySQL | 5.7+ |
| Frontend | HTML5/CSS3/JavaScript | ES6+ |
| Framework CSS | Bootstrap | 5.3 |
| Architecture | MVC | - |
| Sécurité | PDO + Requêtes préparées | - |
| API | REST | - |

---

## 🚀 Installation & Configuration

### Prérequis
- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache/Nginx)
- XAMPP v7.4+ (recommandé)

### Étapes d'installation

#### 1️⃣ Créer la base de données

**Méthode 1 - Terminal MySQL:**
```bash
mysql -u root < sql/database.sql
```

**Méthode 2 - phpMyAdmin:**
1. Accéder à http://localhost/phpmyadmin
2. Créer une base de données nommée `ymmo_db`
3. Importer le fichier `sql/database.sql`

#### 2️⃣ Configurer la connexion

Éditer le fichier `config/database.php`:

```php
private $host = 'localhost';      # Hôte MySQL
private $db_name = 'ymmo_db';     # Nom base de données
private $db_user = 'root';        # Utilisateur MySQL
private $db_pass = '';            # Mot de passe MySQL
```

#### 3️⃣ Vérifier les permissions

```bash
chmod -R 755 public/
chmod -R 755 public/images/
```

#### 4️⃣ Accéder à l'application

Ouvrir dans votre navigateur:
```
http://localhost/fil-rouge/
```

---

## 🔐 Comptes de test

**Note**: Le SQL inclut des comptes de test. Les mots de passe sont hachés. Créez vos propres comptes via la page d'inscription.

| Email | Rôle |
|-------|------|
| admin@ymmo.com | Administrateur |
| jean.dupont@ymmo.com | Agent immobilier |
| marie.martin@ymmo.com | Agent immobilier |

---

## 📚 Endpoints API

### 🔐 Authentification
```
POST   /api/auth/register                    # Inscription
POST   /api/auth/login                       # Connexion
GET    /api/auth/logout                      # Déconnexion
GET    /api/auth/profile                     # Profil utilisateur
POST   /api/auth/profile                     # Modifier profil
POST   /api/auth/change-password             # Changer mot de passe
```

### 🏠 Biens immobiliers
```
GET    /api/properties/list                  # Liste des biens
GET    /api/properties/search                # Rechercher des biens
GET    /api/properties/featured              # Biens en vedette
GET    /api/properties/show?id=1             # Détail d'un bien
POST   /api/properties/create                # Créer un bien
POST   /api/properties/update?id=1           # Modifier un bien
POST   /api/properties/delete?id=1           # Supprimer un bien
GET    /api/properties/by-agent?agent_id=2  # Biens par agent
POST   /api/properties/image-add?property_id # Ajouter image
```

### 👤 Utilisateurs
```
GET    /api/users/agents              # Liste des agents
GET    /api/users/show?id=1           # Détail utilisateur
GET    /api/users/all                 # Tous les utilisateurs (admin)
POST   /api/users/delete?id=1         # Supprimer utilisateur (admin)
```

### 🏢 Agences
```
GET    /api/agencies/list                    # Liste des agences
GET    /api/agencies/show?id=1               # Détail d'une agence
GET    /api/agencies/search?city=Paris       # Rechercher par ville
GET    /api/agencies/stats?id=1              # Statistiques
POST   /api/agencies/create                  # Créer (admin)
POST   /api/agencies/update?id=1             # Modifier (admin)
POST   /api/agencies/delete?id=1             # Supprimer (admin)
```

---

## 💻 Utilisation

### Pour un **Client**
1. S'inscrire sur la plateforme
2. Naviguer dans la liste des biens
3. Utiliser les filtres de recherche
4. Consulter le détail de chaque bien
5. Contacter l'agent

### Pour un **Agent**
1. S'inscrire en tant qu'agent
2. Accéder au tableau de bord
3. Créer et gérer ses annonces
4. Consulter les statistiques

### Pour **l'Administrateur**
1. Gérer tous les utilisateurs
2. Gérer les agences
3. Superviser les transactions
4. Accéder aux statistiques globales

---

## 🔒 Sécurité

### Mesures implémentées

- ✅ Requêtes PDO préparées (protection SQL injection)
- ✅ Hash bcrypt pour les mots de passe
- ✅ Sessions PHP sécurisées
- ✅ Contrôle d'accès basé sur les rôles (RBAC)
- ✅ Validation des données en entrée
- ✅ Validation des types de fichiers
- ✅ Protection contre les attaques XSS

### Bonnes pratiques

1. Toujours valider les entrées utilisateur
2. Utiliser les requêtes préparées PDO
3. Implémenter HTTPS en production
4. Configurer les permissions de fichiers correctement
5. Effectuer des backups réguliers

---

## 📊 Schéma Base de Données

### Table `users`
```sql
- id (INT, PK) - Identifiant unique
- name (VARCHAR) - Nom complet
- email (VARCHAR, UNIQUE) - Email unique
- password (VARCHAR) - Mot de passe hashé
- role (ENUM: client, agent, admin) - Rôle
- phone (VARCHAR) - Téléphone
- city (VARCHAR) - Ville
- is_active (BOOLEAN) - Compte actif
- created_at (TIMESTAMP) - Date création
```

### Table `properties`
```sql
- id (INT, PK) - Identifiant unique
- title (VARCHAR) - Titre du bien
- description (TEXT) - Description
- price (DECIMAL) - Prix
- surface (INT) - Surface en m²
- city (VARCHAR) - Ville
- property_type (ENUM) - Type (house/apartment/land/commercial)
- rooms (INT) - Nombre de pièces
- bathrooms (INT) - Nombre salles bain
- agent_id (INT, FK) - Agent responsable
- agency_id (INT, FK) - Agence
- status (ENUM: available, sold, rented) - Statut
- views_count (INT) - Nombre de vues
- created_at (TIMESTAMP) - Date création
```

### Table `agencies`
```sql
- id (INT, PK) - Identifiant unique
- name (VARCHAR) - Nom de l'agence
- city (VARCHAR) - Ville
- address (TEXT) - Adresse
- phone (VARCHAR) - Téléphone
- email (VARCHAR) - Email
- website (VARCHAR) - Site web
- is_active (BOOLEAN) - Agence active
- created_at (TIMESTAMP) - Date création
```

### Table `transactions`
```sql
- id (INT, PK) - Identifiant unique
- property_id (INT, FK) - Bien immobilier
- buyer_id (INT, FK) - Acheteur
- seller_id (INT, FK) - Vendeur
- price (DECIMAL) - Prix transaction
- status (ENUM) - Statut (pending/completed/cancelled)
- created_at (TIMESTAMP) - Date creation
```

---

## 🛠️ Configuration avancée

### Activer les emails (optionnel)

Pour les notifications, implémenter une classe Mailer:

```php
// Dans models/Mailer.php
class Mailer {
    public static function send($to, $subject, $message) {
        // Implémentation SMTP
    }
}
```

### Intégrer un système de paiement (optionnel)

Pour les transactions:

```php
// controllers/PaymentController.php
class PaymentController {
    // Intégration Stripe, PayPal, etc.
}
```

---

## 🐛 Troubleshooting

### ❌ Erreur: "Connexion à la base de données échouée"

**Solutions:**
- Vérifier les identifiants dans `config/database.php`
- S'assurer que MySQL est en cours d'exécution
- Vérifier que la base `ymmo_db` existe

### ❌ Erreur 404 sur les pages

**Solutions:**
- Vérifier que mod_rewrite est activé (Apache)
- Vérifier les permissions des fichiers
- Vérifier le chemin de base de l'application

### ❌ Problèmes d'upload d'images

**Solutions:**
- Vérifier les permissions du dossier `public/images/`
- Augmenter `upload_max_filesize` dans `php.ini`
- Vérifier le format des images

---

## 📝 Fichiers de configuration importants

| Fichier | Description |
|---------|-------------|
| `config/database.php` | Identifiants MySQL |
| `routes/web.php` | Routeur principal |
| `public/index.php` | Point d'entrée |
| `public/css/style.css` | Styles personnalisés |
| `public/js/script.js` | Logique JavaScript |
| `sql/database.sql` | Schéma et données |

---

## 📞 Support

Pour toute question ou problème:
1. Consulter la documentation
2. Vérifier les logs d'erreur
3. Contacter le support

---

## 📄 Informations et Licence

- **Version**: 1.0.0
- **Licence**: MIT
- **Dernière mise à jour**: 5 mars 2026
- **PHP minimum**: 8.0
- **MySQL minimum**: 5.7
- **Développé avec ❤️**

---

## 🎉 Bienvenue sur Ymmo!

Merci d'utiliser notre plateforme immobilière.

**Bon développement! 🚀**
