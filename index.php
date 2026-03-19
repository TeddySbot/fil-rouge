<?php
session_start();
require 'config/database.php';

// Stats rapides
try {
    $prop_count = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn();
    $ag_count   = $pdo->query("SELECT COUNT(*) FROM agencies WHERE is_active = 1")->fetchColumn();
    $agent_count= $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent'")->fetchColumn();
} catch (Exception $e) {
    $prop_count = $ag_count = $agent_count = 0;
}

require 'includes/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="hero-eyebrow">Trouvez votre chez-vous</div>
    <h1 class="hero-title">L'immobilier <span>simplifié</span><br>pour vous</h1>
    <p class="hero-sub">Parcourez des centaines de biens, connectez-vous aux meilleures agences et trouvez la propriété qui vous correspond.</p>
    <div class="hero-actions">
        <a href="properties.php" class="btn">Voir les propriétés</a>
        <a href="agencies.php" class="btn btn-secondary">Nos agences</a>
    </div>
</section>

<!-- Stats -->
<div class="container" style="padding-top:0">
    <div class="stats-grid" style="max-width:600px;margin:0 auto 60px;">
        <div class="stat-card">
            <div class="stat-val"><?= $prop_count ?></div>
            <div class="stat-lbl">Biens disponibles</div>
        </div>
        <div class="stat-card">
            <div class="stat-val green"><?= $ag_count ?></div>
            <div class="stat-lbl">Agences partenaires</div>
        </div>
        <div class="stat-card">
            <div class="stat-val blue"><?= $agent_count ?></div>
            <div class="stat-lbl">Agents actifs</div>
        </div>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="form-section" style="max-width:520px;margin:0 auto;text-align:center;">
        <p style="margin-bottom:16px;">Connecté en tant que <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['name']) ?></strong></p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="account.php" class="btn btn-secondary">Mon compte</a>
            <?php if ($_SESSION['role'] === 'agent'): ?>
                <a href="agent/index.php" class="btn">Espace agent</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/index.php" class="btn">Administration</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-secondary">Déconnexion</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>