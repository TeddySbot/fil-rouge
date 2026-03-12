<?php
session_start();
require 'config/db.php';

require 'includes/header.php';
?>

<div class="container">
    <h1>Bienvenue sur notre Boutique</h1>

    <?php
    if (isset($_SESSION['user_id'])) {
        echo '<p>Connecté en tant que <strong>' . htmlspecialchars($_SESSION['name']) . '</strong></p>';
        echo '<p><a href="account.php" class="btn">Mon compte</a> <a href="logout.php" class="btn">Déconnexion</a></p>';
    } else {
        echo '<p><a href="login.php" class="btn">Se connecter</a> <a href="register.php" class="btn">S\'inscrire</a></p>';
    }
    ?>

    <hr>
    <h2>[Text]</h2>

</div>

<?php require 'includes/footer.php'; ?>
