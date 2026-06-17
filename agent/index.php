<?php
session_start();
require '../config/database.php';

use App\Repository\AgencyRepository;
use App\Repository\PropertyRepository;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id    = $_SESSION['user_id'];
$error       = null;
$agent_agency = null;

try {
    // Couche POO : repositories à requêtes préparées (corrige l'ancienne
    // injection SQL où $agency_id était interpolé directement dans le SQL).
    $agencyRepo   = new AgencyRepository($pdo);
    $propertyRepo = new PropertyRepository($pdo);

    $agency = $agencyRepo->findByAgent($agent_id);

    $props_total = $props_available = $props_sold = 0;
    if ($agency !== null) {
        // Conserve la forme attendue par la vue.
        $agent_agency = ['agency_id' => $agency->id, 'agency_name' => $agency->name];

        $props_total     = $propertyRepo->countByAgency($agency->id);
        $props_available = $propertyRepo->countByAgency($agency->id, 'available');
        $props_sold      = $propertyRepo->countByAgency($agency->id, 'sold');
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
            <a href="stats.php" class="dash-card" onclick="window.location='stats.php';return false;">
                <div class="dash-card-icon" style="background:rgba(62,207,116,.1);border-color:rgba(62,207,116,.2);color:var(--green);">📊</div>
                <div class="dash-card-label">Voir les Statistiques</div>
                <div class="dash-card-desc">Consulter les performances de mon agence</div>
            </a>
            <a href="analytics.php" class="dash-card" onclick="window.location='analytics.php';return false;">
                <div class="dash-card-icon" style="background:rgba(212,168,67,.1);border-color:rgba(212,168,67,.2);color:var(--gold);">📈</div>
                <div class="dash-card-label">Analyse de données</div>
                <div class="dash-card-desc">Rapports de ventes &amp; prévisions (Python)</div>
            </a>
            <a href="agent_agenda.php" class="dash-card" onclick="window.location='agent_agenda.php';return false;">
                <div class="dash-card-icon" style="background:rgba(62,207,116,.1);border-color:rgba(62,207,116,.2);color:var(--green);">📅</div>
                <div class="dash-card-label">Agenda</div>
                <div class="dash-card-desc">Gérer mes rendez-vous</div>
            </a>
            <a href="../agent_messages.php" class="dash-card" onclick="window.location='../messages.php';return false;">
                <div class="dash-card-icon" style="background:rgba(62,207,116,.1);border-color:rgba(62,207,116,.2);color:var(--green);">✉️</div>
                <div class="dash-card-label">Mes Messages</div>
                <div class="dash-card-desc">Messages reçus de clients</div>
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