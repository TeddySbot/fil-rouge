<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');

            if (!$name) {
                $error = "Le nom est requis";
            } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email invalide";
            } elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) {
                $error = "Le numéro de téléphone n'est pas valide";
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET name = :name, email = :email, phone = :phone, address = :address, city = :city WHERE id = :id"
                );
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'address' => $address ?: null,
                    'city' => $city ?: null,
                    'id' => $_SESSION['user_id']
                ]);
                $_SESSION['name'] = $name;
                $success = "Profil mis à jour";
                $user = $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute(['id' => $_SESSION['user_id']]);
                $user = $stmt->fetch();
            }
        } elseif ($_POST['action'] === 'update_password') {
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (!password_verify($old_password, $user['password'])) {
                $error = "Ancien mot de passe incorrect";
            } elseif (strlen($new_password) < 6) {
                $error = "Le nouveau mot de passe doit faire au minimum 6 caractères";
            } elseif ($new_password !== $confirm_password) {
                $error = "Les mots de passe ne correspondent pas";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute(['password' => password_hash($new_password, PASSWORD_DEFAULT), 'id' => $_SESSION['user_id']]);
                $success = "Mot de passe mis à jour";
            }
        }
    }
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
}

require 'includes/header.php';
?>

<div class="container">
    <h1>Mon Compte</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="account-info">
        <h2>Informations du compte</h2>
        <p><strong>Nom:</strong> <?= htmlspecialchars($user['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Téléphone:</strong> <?= htmlspecialchars($user['phone'] ?? 'Non renseigné') ?></p>
        <p><strong>Adresse:</strong> <?= htmlspecialchars($user['address'] ?? 'Non renseignée') ?></p>
        <p><strong>Ville:</strong> <?= htmlspecialchars($user['city'] ?? 'Non renseignée') ?></p>
        <p><strong>Rôle:</strong> <?= htmlspecialchars(ucfirst($user['role'])) ?></p>
        <p><strong>Statut:</strong> <?= $user['is_active'] ? '<span style="color: green;">Actif</span>' : '<span style="color: red;">Inactif</span>' ?></p>
        <p><strong>Inscrit depuis:</strong> <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
    </div>

    <hr>
    <h2>Modifier mon compte</h2>

    <div class="form-section">
        <h3>Mettre à jour mon profil</h3>
        <form method="post">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Téléphone</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="address">Adresse</label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="city">Ville</label>
                <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
            </div>
            <button type="submit" class="btn">Mettre à jour</button>
        </form>
    </div>

    <div class="form-section">
        <h3>Changer le mot de passe</h3>
        <form method="post">
            <input type="hidden" name="action" value="update_password">
            <div class="form-group">
                <label for="old_password">Ancien mot de passe</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Mettre à jour</button>
        </form>
    </div>

    <hr>
    <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
</div>

<?php require 'includes/footer.php'; ?>
