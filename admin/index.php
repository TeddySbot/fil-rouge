<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$stats = ['total'=>0,'client'=>0,'attente'=>0,'agent'=>0,'admin'=>0,'properties'=>0,'agencies'=>0];
try {
    $rows = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $stats[$r['role']] = $r['c']; $stats['total'] += $r['c']; }
    $stats['properties'] = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
    $stats['agencies']   = $pdo->query("SELECT COUNT(*) FROM agencies WHERE is_active=1")->fetchColumn();
} catch (PDOException $e) {}

require '../includes/header.php';
?>

<div class="container">

    <div class="dash-header">
        <div>
            <div class="page-eyebrow">Administration</div>
            <h1 style="margin-bottom:0">Tableau de bord</h1>
        </div>
        <span style="font-size:13px;color:var(--muted)">Connecté en tant qu'<strong style="color:var(--gold)">Admin</strong></span>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:36px;">
        <div class="stat-card">
            <div class="stat-val soft"><?= $stats['total'] ?></div>
            <div class="stat-lbl">Utilisateurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $stats['client'] ?></div>
            <div class="stat-lbl">Clients</div>
        </div>
        <div class="stat-card">
            <div class="stat-val <?= $stats['attente'] > 0 ? '' : 'soft' ?>"><?= $stats['attente'] ?></div>
            <div class="stat-lbl">En attente</div>
        </div>
        <div class="stat-card">
            <div class="stat-val green"><?= $stats['agent'] ?></div>
            <div class="stat-lbl">Agents</div>
        </div>
        <div class="stat-card">
            <div class="stat-val blue"><?= $stats['properties'] ?></div>
            <div class="stat-lbl">Propriétés</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $stats['agencies'] ?></div>
            <div class="stat-lbl">Agences</div>
        </div>
    </div>

    <!-- Menu -->
    <div class="block-title">Gestion</div>
    <div class="dash-menu">
        <a href="users.php" class="dash-card">
            <div class="dash-card-icon">👥</div>
            <div class="dash-card-label">Utilisateurs</div>
            <div class="dash-card-desc">Gérer les comptes, rôles et statuts</div>
        </a>
        <a href="approve.php" class="dash-card" style="<?= $stats['attente'] > 0 ? 'border-color:var(--gold-dim)' : '' ?>">
            <div class="dash-card-icon" style="<?= $stats['attente'] > 0 ? 'background:rgba(212,168,67,.15);' : '' ?>">
                <?= $stats['attente'] > 0 ? '🔔' : '✅' ?>
            </div>
            <div class="dash-card-label">Approbations</div>
            <div class="dash-card-desc">
                <?= $stats['attente'] > 0 ? "<strong style='color:var(--gold)'>{$stats['attente']}</strong> candidature(s) en attente" : 'Aucune candidature en attente' ?>
            </div>
        </a>
        <a href="../agencies.php" class="dash-card">
            <div class="dash-card-icon">🏢</div>
            <div class="dash-card-label">Agences</div>
            <div class="dash-card-desc">Voir les agences actives</div>
        </a>
        <a href="../account.php" class="dash-card">
            <div class="dash-card-icon">👤</div>
            <div class="dash-card-label">Mon profil</div>
            <div class="dash-card-desc">Modifier mes informations</div>
        </a>
    </div>

    <a href="../index.php" class="btn btn-secondary">← Accueil</a>
</div>

<?php require '../includes/footer.php'; ?>