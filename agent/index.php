<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id    = $_SESSION['user_id'];
$error       = null;
$agent_agency = null;

try {
    $stmt = $pdo->prepare("SELECT aa.*, a.name AS agency_name, a.id AS agency_id FROM agency_agents aa JOIN agencies a ON aa.agency_id = a.id WHERE aa.agent_id = :id LIMIT 1");
    $stmt->execute(['id' => $agent_id]);
    $agent_agency = $stmt->fetch();

    // Stats
    $agency_id = $agent_agency['agency_id'] ?? null;
    $props_total = $props_available = $props_sold = 0;
    if ($agency_id) {
        $props_total     = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agency_id = ?")->execute([$agency_id]) ? $pdo->query("SELECT COUNT(*) FROM properties WHERE agency_id = $agency_id")->fetchColumn() : 0;
        $props_available = $pdo->query("SELECT COUNT(*) FROM properties WHERE agency_id = $agency_id AND status = 'available'")->fetchColumn();
        $props_sold      = $pdo->query("SELECT COUNT(*) FROM properties WHERE agency_id = $agency_id AND status = 'sold'")->fetchColumn();
    }
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

require '../includes/header.php';
?>

<div class="container">

    <div class="dash-header">
        <div>
            <div class="page-eyebrow">Espace</div>
            <h1 style="margin-bottom:0">Tableau de bord agent</h1>
        </div>
        <span style="font-size:13px;color:var(--muted)">Bonjour, <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['name']) ?></strong></span>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($agent_agency): ?>
    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-val soft"><?= $props_total ?></div>
            <div class="stat-lbl">Total biens</div>
        </div>
        <div class="stat-card">
            <div class="stat-val green"><?= $props_available ?></div>
            <div class="stat-lbl">Disponibles</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $props_sold ?></div>
            <div class="stat-lbl">Vendus</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Menu -->
    <div class="block-title">Actions</div>
    <div class="dash-menu">
        <?php if ($agent_agency): ?>
            <a href="../agency_detail.php?id=<?= $agent_agency['agency_id'] ?>" class="dash-card">
                <div class="dash-card-icon">🏢</div>
                <div class="dash-card-label"><?= htmlspecialchars($agent_agency['agency_name']) ?></div>
                <div class="dash-card-desc">Voir la fiche de mon agence</div>
            </a>
            <a href="properties.php" class="dash-card">
                <div class="dash-card-icon">🏠</div>
                <div class="dash-card-label">Gérer mes biens</div>
                <div class="dash-card-desc">Liste, ajout, modification</div>
            </a>
            <a href="properties.php?action=new" class="dash-card" onclick="window.location='property_create.php';return false;">
                <div class="dash-card-icon" style="background:rgba(62,207,116,.1);border-color:rgba(62,207,116,.2);color:var(--green);">+</div>
                <div class="dash-card-label">Ajouter un bien</div>
                <div class="dash-card-desc">Créer une nouvelle propriété</div>
            </a>
        <?php else: ?>
            <a href="join_agency.php" class="dash-card">
                <div class="dash-card-icon">🔗</div>
                <div class="dash-card-label">Rejoindre une agence</div>
                <div class="dash-card-desc">Associez-vous à une agence existante</div>
            </a>
            <a href="createdagence.php" class="dash-card">
                <div class="dash-card-icon">🏢</div>
                <div class="dash-card-label">Créer une agence</div>
                <div class="dash-card-desc">Fondez votre propre agence</div>
            </a>
        <?php endif; ?>
        <a href="../account.php" class="dash-card">
            <div class="dash-card-icon">👤</div>
            <div class="dash-card-label">Mon profil</div>
            <div class="dash-card-desc">Modifier mes informations</div>
        </a>
    </div>

    <a href="../index.php" class="btn btn-secondary">← Accueil</a>
</div>

<?php require '../includes/footer.php'; ?>