<?php
session_start();
require 'config/database.php';

$error = null;
$agencies = [];

try {
    $stmt = $pdo->query(
        "SELECT * FROM agencies WHERE is_active = 1 ORDER BY name ASC"
    );
    $agencies = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
}

require 'includes/header.php';
?>

<div class="container">
    <h1>Nos agences</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($agencies)): ?>
        <p>Aucune agence disponible pour le moment.</p>
    <?php else: ?>
        <div class="agencies-grid">
            <?php foreach ($agencies as $agency): ?>
                <div class="agency-card">
                    <h2><?= htmlspecialchars($agency['name']) ?></h2>
                    
                    <p><strong>Ville:</strong> <?= htmlspecialchars($agency['city']) ?></p>
                    
                    <?php if ($agency['address']): ?>
                        <p><strong>Adresse:</strong> <?= htmlspecialchars($agency['address']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($agency['phone']): ?>
                        <p><strong>Téléphone:</strong> <a href="tel:<?= htmlspecialchars($agency['phone']) ?>"><?= htmlspecialchars($agency['phone']) ?></a></p>
                    <?php endif; ?>
                    
                    <?php if ($agency['email']): ?>
                        <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($agency['email']) ?>"><?= htmlspecialchars($agency['email']) ?></a></p>
                    <?php endif; ?>
                    
                    <?php if ($agency['website']): ?>
                        <p><strong>Site web:</strong> <a href="<?= htmlspecialchars($agency['website']) ?>" target="_blank"><?= htmlspecialchars($agency['website']) ?></a></p>
                    <?php endif; ?>
                    
                    <?php if ($agency['description']): ?>
                        <p><strong>Description:</strong> <?= htmlspecialchars($agency['description']) ?></p>
                    <?php endif; ?>
                    
                    <a href="agency_detail.php?id=<?= $agency['id'] ?>" class="btn">Voir plus</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">Retour</a>
</div>
