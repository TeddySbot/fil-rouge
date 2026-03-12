<?php
session_start();
require 'config/database.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $role = $_POST['role'] ?? 'client';

    // Validation
    if (!$name) {
        $error = "Le nom est requis";
    } elseif (!$email) {
        $error = "L'email est requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer un email valide";
    } elseif (!$password) {
        $error = "Le mot de passe est requis";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit faire au minimum 6 caractères";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) {
        $error = "Le numéro de téléphone n'est pas valide";
    } elseif (!in_array($role, ['client', 'agent', 'admin'])) {
        $role = 'client';
    }

    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé";
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, phone, address, city, role, profile_image, is_active)
                     VALUES (:name, :email, :password, :phone, :address, :city, :role, 'default.png', 1)"
                );

                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone ?: null,
                    'address' => $address ?: null,
                    'city' => $city ?: null,
                    'role' => $role
                ]);

                $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = "Erreur lors de l'inscription: " . $e->getMessage();
            }
        }
    }
}

require 'includes/header.php';
?>

<div class="auth-container">
    <h1>Inscription</h1>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="name">Nom complet *</label>
            <input type="text" id="name" name="name" placeholder="Votre nom complet" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" placeholder="Votre email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe *</label>
            <input type="password" id="password" name="password" placeholder="Minimum 6 caractères" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmer le mot de passe *</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirmez votre mot de passe" required>
        </div>

        <div class="form-group">
            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone" placeholder="Votre numéro de téléphone">
        </div>

        <div class="form-group">
            <label for="address">Adresse</label>
            <input type="text" id="address" name="address" placeholder="Votre adresse">
        </div>

        <div class="form-group">
            <label for="city">Ville</label>
            <input type="text" id="city" name="city" placeholder="Votre ville">
        </div>

        <div class="form-group">
            <label for="role">Type de compte</label>
            <select id="role" name="role">
                <option value="client">Client</option>
                <option value="agent">Agent</option>
            </select>
        </div>
        
        <button type="submit" class="btn">S'inscrire</button>
    </form>

    <p>Vous avez déjà un compte? <a href="login.php">Se connecter</a></p>
</div>

<?php require 'includes/footer.php'; ?>
