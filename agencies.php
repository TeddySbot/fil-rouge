<?php
session_start();
require 'config/database.php';

$error    = null;
$agencies = [];
$search   = trim($_GET['q'] ?? '');

try {
    $sql    = "SELECT a.*, (SELECT COUNT(*) FROM agency_agents aa WHERE aa.agency_id = a.id) AS agent_count FROM agencies a WHERE a.is_active = 1";
    $params = [];
    if ($search) {
        $sql   .= " AND (a.name LIKE ? OR a.city LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    $sql .= " ORDER BY a.name ASC";
    $stmt  = $pdo->prepare($sql);
    $stmt->execute($params);
    $agencies = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

require 'includes/header.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <div class="page-eyebrow">Annuaire</div>
            <h1 style="margin-bottom:0">Nos Agences</h1>
        </div>
        <span style="font-size:13px;color:var(--muted)"><?= count($agencies) ?> agence<?= count($agencies) > 1 ? 's' : '' ?></span>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Recherche -->
    <form method="GET" action="">
        <div class="filter-bar" style="margin-bottom:28px;">
            <svg width="14" height="14" fill="none" stroke="var(--muted)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="Rechercher par nom ou ville…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">
            <button type="submit" class="btn btn-sm">Rechercher</button>
            <?php if ($search): ?><a href="agencies.php" class="btn btn-secondary btn-sm">✕ Effacer</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($agencies)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;opacity:.3"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <p>Aucune agence disponible pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="agencies-grid">
            <?php foreach ($agencies as $i => $agency): ?>
                <div class="agency-card" style="animation-delay:<?= min($i*0.05, 0.3) ?>s">
                    <!-- Logo -->
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                        <div style="width:48px;height:48px;border-radius:10px;background:var(--surf2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--gold);flex-shrink:0;overflow:hidden;">
                            <?php if ($agency['logo'] && $agency['logo'] !== 'default-agency.png'): ?>
                                <img src="<?= htmlspecialchars($agency['logo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?= strtoupper(substr($agency['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 style="font-size:16px;margin-bottom:2px;"><?= htmlspecialchars($agency['name']) ?></h2>
                            <span class="badge badge-gray">
                                <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <?= $agency['agent_count'] ?> agent<?= $agency['agent_count'] > 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($agency['city']): ?>
                        <p><strong>📍</strong> <?= htmlspecialchars($agency['city']) ?></p>
                    <?php endif; ?>
                    <?php if ($agency['phone']): ?>
                        <p><strong>📞</strong> <a href="tel:<?= htmlspecialchars($agency['phone']) ?>"><?= htmlspecialchars($agency['phone']) ?></a></p>
                    <?php endif; ?>
                    <?php if ($agency['email']): ?>
                        <p><strong>✉️</strong> <a href="mailto:<?= htmlspecialchars($agency['email']) ?>"><?= htmlspecialchars($agency['email']) ?></a></p>
                    <?php endif; ?>
                    <?php if ($agency['description']): ?>
                        <p style="font-size:12px;color:var(--muted);-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= htmlspecialchars($agency['description']) ?>
                        </p>
                    <?php endif; ?>

                    <a href="agency_detail.php?id=<?= $agency['id'] ?>" class="btn">Voir l'agence</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">← Retour à l'accueil</a>
</div>

<?php require 'includes/footer.php'; ?>