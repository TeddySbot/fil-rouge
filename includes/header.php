<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠 Loge-Moi</title>
    <?php
        $path = $_SERVER['PHP_SELF'];
        if (strpos($path, '/admin/') !== false || strpos($path, '/agent/') !== false) {
            $base = '../';
        } else {
            $base = '';
        }
    ?>
    <link rel="stylesheet" href="<?php echo $base; ?>public/css/style.css">
</head>
<?php
    $role = $_SESSION['role'] ?? 'guest';
    $body_class = match($role) {
        'admin'  => 'theme-admin',
        'agent'  => 'theme-agent',
        default  => 'theme-default',
    };
?>
<body class="<?= $body_class ?>">
<header>
    <nav class="navbar">
        <div class="logo">
            <a href="<?php echo $base; ?>index.php">🏠 Loge-Moi</a>
        </div>

        <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <ul class="nav-links">
            <li><a href="<?php echo $base; ?>index.php">Accueil</a></li>
            <li><a href="<?php echo $base; ?>properties.php">Propriétés</a></li>
            <li><a href="<?php echo $base; ?>agencies.php">Agences</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="<?php echo $base; ?>account.php">Mon compte</a></li>
                <?php if ($_SESSION['role'] === 'agent'): ?>
                    <li><a href="<?php echo $base; ?>agent/index.php">Espace agent</a></li>
                <?php endif; ?>
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