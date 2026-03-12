<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$users_count = 0;


try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $users_count = $result['count'] ?? 0;

    

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

require '../includes/header.php';
?>

<div class="container">
    <h1>Panneau Administrateur</h1>

    <div class="admin-menu">
        <h2>Gestion</h2>
        <ul>
            <li><a href="users.php" class="btn">Gérer les utilisateurs</a></li>
            <li><a href="properties.php" class="btn">Gérer les propriétés</a></li>
        </ul>
    </div>

    <div class="admin-stats">
        <h2>Statistiques</h2>
        <p>Total utilisateurs: <strong><?= $users_count ?></strong></p>
        <p>Total agents: <strong><?= $agents_count ?></strong></p>
    </div>

    <a href="../index.php" class="btn btn-secondary">Accueil</a>
</div>

<?php require '../includes/footer.php'; ?>
