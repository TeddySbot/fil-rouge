<?php
session_start();
require 'config/db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password, role, is_active FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = "Ce compte a été désactivé";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                header('Location: index.php');
                exit;
            }
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    }
}

require 'includes/header.php';
?>

<div class="auth-container">
    <h1>Connexion</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Votre email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
        </div>
        
        <button type="submit" class="btn">Se connecter</button>
    </form>

    <p>Vous n'avez pas de compte? <a href="register.php">S'inscrire</a></p>
</div>

<?php require 'includes/footer.php'; ?>
