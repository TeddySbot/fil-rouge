<?php
session_start();
require 'config/database.php';

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $city             = trim($_POST['city'] ?? '');
    $role             = $_POST['role'] ?? 'client';

    if (!$name)                                           $error = "Le nom est requis.";
    elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Adresse email invalide.";
    elseif (!$password)                                   $error = "Le mot de passe est requis.";
    elseif (strlen($password) < 6)                        $error = "Le mot de passe doit faire au moins 6 caractères.";
    elseif ($password !== $confirm_password)              $error = "Les mots de passe ne correspondent pas.";
    elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) $error = "Numéro de téléphone invalide.";

    if (!in_array($role, ['client', 'attente'])) $role = 'client';

    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address, city, role, profile_image, is_active) VALUES (:name, :email, :password, :phone, :address, :city, :role, 'default.png', 1)");
                $stmt->execute([
                    'name' => $name, 'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone ?: null, 'address' => $address ?: null,
                    'city' => $city ?: null, 'role' => $role
                ]);
                $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['role']    = $user['role'];
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = "Erreur lors de l'inscription : " . $e->getMessage();
            }
        }
    }
}

require 'includes/header.php';
?>

<div class="auth-wrap" style="align-items:flex-start;padding:40px 24px;">
    <div class="auth-card" style="max-width:520px;">
        <div class="auth-logo">🏠 Loge-Moi</div>
        <p class="auth-subtitle">Créez votre compte</p>

        <?php if ($error): ?>
            <div class="error">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <!-- Grid 2 cols -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
                <div class="form-group" style="grid-column:span 2">
                    <label>Nom complet <span class="req">*</span></label>
                    <input type="text" name="name" placeholder="Jean Dupont" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" placeholder="votre@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe <span class="req">*</span></label>
                    <input type="password" name="password" placeholder="Min. 6 caractères" required>
                </div>
                <div class="form-group">
                    <label>Confirmer <span class="req">*</span></label>
                    <input type="password" name="confirm_password" placeholder="Répétez le mot de passe" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="phone" placeholder="06 00 00 00 00" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Ville</label>
                    <input type="text" name="city" placeholder="Paris" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Adresse</label>
                    <input type="text" name="address" placeholder="12 rue des Fleurs" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Type de compte</label>
                    <select name="role">
                        <option value="client" <?= ($_POST['role'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="attente" <?= ($_POST['role'] ?? '') === 'attente' ? 'selected' : '' ?>>Agent (en attente de validation)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn" style="width:100%;justify-content:center;padding:13px;margin-top:4px;">Créer mon compte</button>
        </form>

        <p class="auth-footer">Déjà un compte ? <a href="login.php">Se connecter</a></p>
    </div>
</div>

<?php require 'includes/footer.php'; ?>