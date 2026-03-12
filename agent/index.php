<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare(
        "SELECT aa.*, a.name as agency_name 
         FROM agency_agents aa 
         JOIN agencies a ON aa.agency_id = a.id 
         WHERE aa.agent_id = :agent_id 
         LIMIT 1"
    );
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $agent_agency = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
    $agent_agency = null;
}

require '../includes/header.php';
?>

<div class="container">
    <h1>Panneau Agent</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="admin-menu">
        <h2>Gestion</h2>
        <ul>
            <?php if ($agent_agency): ?>
                <li>
                    <a href="agency.php?id=<?= $agent_agency['agency_id'] ?>" class="btn">
                        Mon agence: <?= htmlspecialchars($agent_agency['agency_name']) ?>
                    </a>
                </li>
            <?php else: ?>
                <li><a href="join_agency.php" class="btn">Rejoindre une agence</a></li>
                <li><a href="createdagence.php" class="btn">Créer une agence</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <a href="../index.php" class="btn btn-secondary">Accueil</a>
</div>

<?php require '../includes/footer.php'; ?>
