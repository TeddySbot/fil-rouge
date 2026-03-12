<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loge-Moi</title>
    <?php
        $path = $_SERVER['PHP_SELF'];
        if (strpos($path, '/admin/') !== false) {
            $base = '../';
        } elseif (strpos($path, '/cart/') !== false) {
            $base = '../';
        } else {
            $base = '';
        }
    ?>
    <link rel="stylesheet" href="<?php echo $base; ?>public/css/style.css">
</head>
<body>
<header>
    <nav class="navbar">
        <div class="logo"><a href="<?php echo $base; ?>index.php">Loge-Moi</a></div>
        <ul class="nav-links">
            <li><a href="<?php echo $base; ?>index.php">Accueil</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="<?php echo $base; ?>account.php">Mon compte</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="<?php echo $base; ?>admin/index.php">Admin</a></li>
                <?php endif; ?>
                <li><a href="<?php echo $base; ?>logout.php">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="<?php echo $base; ?>login.php">Connexion</a></li>
                <li><a href="<?php echo $base; ?>register.php">Inscription</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main>
