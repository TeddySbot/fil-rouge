<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id = $_SESSION['user_id'];

// Vérifier que l'agent n'est pas déjà dans une agence
$stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
if ($stmt->fetch()) {
    header('Location: index.php');
    exit;
}

$error   = null;
$success = null;
$search  = trim($_GET['q'] ?? '');

// Traitement POST : rejoindre une agence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agency_id'])) {
    $agency_id = (int)$_POST['agency_id'];
    try {
        // Vérifier que l'agence existe et est active
        $check = $pdo->prepare("SELECT id, name FROM agencies WHERE id = ? AND is_active = 1 LIMIT 1");
        $check->execute([$agency_id]);
        $agency = $check->fetch();

        if (!$agency) {
            $error = "Agence introuvable ou inactive.";
        } else {
            $pdo->prepare("INSERT INTO agency_agents (agency_id, agent_id) VALUES (?, ?)")
                ->execute([$agency_id, $agent_id]);
            header('Location: index.php?joined=1');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Rechercher les agences disponibles
$sql    = "SELECT a.*, (SELECT COUNT(*) FROM agency_agents aa WHERE aa.agency_id = a.id) AS agent_count FROM agencies a WHERE a.is_active = 1";
$params = [];
if ($search) {
    $sql   .= " AND (a.name LIKE ? OR a.city LIKE ?)";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY a.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '../includes/header.php';
?>

<div class="ja-page">
<div class="ja-wrap">

    <!-- Back -->
    <a href="index.php" class="ja-back">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour au tableau de bord
    </a>

    <!-- Header -->
    <div class="ja-header">
        <div class="page-eyebrow">Espace agent</div>
        <h1>Rejoindre une agence</h1>
        <p style="color:var(--soft);font-size:14px;margin-bottom:0;margin-top:-12px;">
            Sélectionnez l'agence à laquelle vous souhaitez vous rattacher.
        </p>
    </div>

    <?php if ($error): ?>
        <div class="error">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Recherche -->
    <form method="GET" action="">
        <div class="filter-bar" style="margin-bottom:28px;">
            <svg width="14" height="14" fill="none" stroke="var(--muted)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="Rechercher par nom ou ville…" value="<?= htmlspecialchars($search) ?>" style="flex:1;">
            <button type="submit" class="btn btn-sm">Rechercher</button>
            <?php if ($search): ?>
                <a href="join_agency.php" class="btn btn-secondary btn-sm">✕ Effacer</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Compteur -->
    <p class="ja-count">
        <strong><?= count($agencies) ?></strong> agence<?= count($agencies) > 1 ? 's' : '' ?> disponible<?= count($agencies) > 1 ? 's' : '' ?>
    </p>

    <!-- Grille agences -->
    <?php if (empty($agencies)): ?>
        <div class="ja-empty">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <p>Aucune agence trouvée.</p>
            <?php if ($search): ?>
                <a href="join_agency.php" class="btn btn-secondary btn-sm">Voir toutes les agences</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ja-grid">
            <?php foreach ($agencies as $i => $ag): ?>
                <div class="ja-card" style="animation-delay:<?= min($i * 0.05, 0.4) ?>s">

                    <!-- Infos agence -->
                    <div class="ja-card-top">
                        <div class="ja-logo">
                            <?php if ($ag['logo'] && $ag['logo'] !== 'default-agency.png'): ?>
                                <img src="../<?= htmlspecialchars($ag['logo']) ?>" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($ag['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="ja-card-info">
                            <div class="ja-card-name"><?= htmlspecialchars($ag['name']) ?></div>
                            <div class="ja-card-meta">
                                <span>
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                                    <?= htmlspecialchars($ag['city']) ?>
                                </span>
                                <span>
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    <?= $ag['agent_count'] ?> agent<?= $ag['agent_count'] > 1 ? 's' : '' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Coordonnées -->
                    <div class="ja-card-coords">
                        <?php if ($ag['phone']): ?>
                            <div class="ja-coord">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.28 19a19.45 19.45 0 0 1-6-6 19.79 19.79 0 0 1-3.93-8.56A2 2 0 0 1 3.22 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6z"/></svg>
                                <?= htmlspecialchars($ag['phone']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($ag['email']): ?>
                            <div class="ja-coord">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <?= htmlspecialchars($ag['email']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($ag['address']): ?>
                            <div class="ja-coord">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <?= htmlspecialchars($ag['address']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($ag['description']): ?>
                        <p class="ja-card-desc"><?= htmlspecialchars(mb_strimwidth($ag['description'], 0, 100, '…')) ?></p>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="ja-card-footer">
                        <a href="../agency_detail.php?id=<?= $ag['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Voir la fiche
                        </a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="agency_id" value="<?= $ag['id'] ?>">
                            <button type="submit" class="btn btn-sm ja-join-btn"
                                onclick="return confirm('Rejoindre l\'agence <?= htmlspecialchars(addslashes($ag['name'])) ?> ?')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Rejoindre
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Créer une agence -->
    <div class="ja-create-cta">
        <div class="ja-create-cta-inner">
            <div>
                <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px;">
                    Vous ne trouvez pas votre agence ?
                </div>
                <div style="font-size:13px;color:var(--muted);">
                    Créez votre propre agence et commencez à gérer vos biens.
                </div>
            </div>
            <a href="createdagence.php" class="btn">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Créer une agence
            </a>
        </div>
    </div>

</div>
</div>

<?php require '../includes/footer.php'; ?>