<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php'); exit;
}

$agent_id = $_SESSION['user_id'];
$period   = isset($_GET['period']) && in_array($_GET['period'], ['7','30','90','365']) ? (int)$_GET['period'] : 30;

// Agence
$stmt = $pdo->prepare("SELECT aa.agency_id, a.name AS agency_name FROM agency_agents aa JOIN agencies a ON a.id = aa.agency_id WHERE aa.agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
$agency = $stmt->fetch(PDO::FETCH_ASSOC);
$agency_id = $agency['agency_id'] ?? null;

if (!$agency_id) { header('Location: index.php'); exit; }

// ── Stats générales ──
$total_props     = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agency_id = ?"); $total_props->execute([$agency_id]); $total_props = $total_props->fetchColumn();
$available_props = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agency_id = ? AND status = 'available'"); $available_props->execute([$agency_id]); $available_props = $available_props->fetchColumn();
$sold_props      = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agency_id = ? AND status = 'sold'"); $sold_props->execute([$agency_id]); $sold_props = $sold_props->fetchColumn();
$rented_props    = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agency_id = ? AND status = 'rented'"); $rented_props->execute([$agency_id]); $rented_props = $rented_props->fetchColumn();
$total_views     = $pdo->prepare("SELECT COALESCE(SUM(views_count),0) FROM properties WHERE agency_id = ?"); $total_views->execute([$agency_id]); $total_views = $total_views->fetchColumn();
$avg_price       = $pdo->prepare("SELECT COALESCE(AVG(price),0) FROM properties WHERE agency_id = ? AND status = 'available'"); $avg_price->execute([$agency_id]); $avg_price = $avg_price->fetchColumn();

// Mes stats perso (propriétés dont je suis l'agent)
$my_props  = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ?"); $my_props->execute([$agent_id]); $my_props = $my_props->fetchColumn();
$my_views  = $pdo->prepare("SELECT COALESCE(SUM(views_count),0) FROM properties WHERE agent_id = ?"); $my_views->execute([$agent_id]); $my_views = $my_views->fetchColumn();
$my_sold   = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status IN ('sold','rented')"); $my_sold->execute([$agent_id]); $my_sold = $my_sold->fetchColumn();

// Rendez-vous sur la période
$appts_period = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE agent_id = ? AND scheduled_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$appts_period->execute([$agent_id, $period]); $appts_period = $appts_period->fetchColumn();

$appts_pending = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE agent_id = ? AND status = 'pending'");
$appts_pending->execute([$agent_id]); $appts_pending = $appts_pending->fetchColumn();

// Top 5 biens les plus vus
$top_props = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.price, p.status, p.views_count,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS img
    FROM properties p WHERE p.agency_id = ?
    ORDER BY p.views_count DESC LIMIT 5
");
$top_props->execute([$agency_id]);
$top_props = $top_props->fetchAll(PDO::FETCH_ASSOC);

// Biens ajoutés sur la période (par mois pour le graphe)
$monthly = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
    FROM properties WHERE agency_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
");
$monthly->execute([$agency_id]);
$monthly = $monthly->fetchAll(PDO::FETCH_ASSOC);

// Messages non lus
$unread = $pdo->prepare("
    SELECT COUNT(*) FROM messages m
    JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
    WHERE m.sender_id != ? AND (cp.last_read_at IS NULL OR m.created_at > cp.last_read_at)
");
$unread->execute([$agent_id, $agent_id]); $unread = $unread->fetchColumn();

$status_colors = ['available' => 'green', 'sold' => 'gold', 'rented' => 'blue'];
$status_labels = ['available' => 'Disponible', 'sold' => 'Vendu', 'rented' => 'Loué'];

require '../includes/header.php';
?>

<div class="container">
  <div class="dash-header">
    <div>
      <div class="page-eyebrow"><?= htmlspecialchars($agency['agency_name']) ?></div>
      <h1 style="margin-bottom:0">Statistiques</h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach ([7=>'7j',30=>'30j',90=>'90j',365=>'1an'] as $val => $lbl): ?>
        <a href="?period=<?= $val ?>" class="btn btn-sm <?= $period === $val ? '' : 'btn-secondary' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Stats agence -->
  <div class="block-title">Mon agence</div>
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:32px;">
    <div class="stat-card"><div class="stat-val soft"><?= $total_props ?></div><div class="stat-lbl">Total biens</div></div>
    <div class="stat-card"><div class="stat-val green"><?= $available_props ?></div><div class="stat-lbl">Disponibles</div></div>
    <div class="stat-card"><div class="stat-val"><?= $sold_props ?></div><div class="stat-lbl">Vendus</div></div>
    <div class="stat-card"><div class="stat-val blue"><?= $rented_props ?></div><div class="stat-lbl">Loués</div></div>
  </div>

  <!-- Stats perso + activité -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">

    <!-- Mes perfs -->
    <div class="form-section" style="margin:0">
      <div class="block-title">Mes performances</div>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="st-row">
          <span class="st-label">Mes biens gérés</span>
          <span class="st-val"><?= $my_props ?></span>
        </div>
        <div class="st-row">
          <span class="st-label">Vues totales</span>
          <span class="st-val"><?= number_format($my_views, 0, ',', ' ') ?></span>
        </div>
        <div class="st-row">
          <span class="st-label">Vendus / Loués</span>
          <span class="st-val" style="color:var(--green)"><?= $my_sold ?></span>
        </div>
        <div class="st-row">
          <span class="st-label">Prix moyen agence</span>
          <span class="st-val"><?= number_format($avg_price, 0, ',', ' ') ?> €</span>
        </div>
        <div class="st-row">
          <span class="st-label">Vues totales agence</span>
          <span class="st-val"><?= number_format($total_views, 0, ',', ' ') ?></span>
        </div>
      </div>
    </div>

    <!-- Activité période -->
    <div class="form-section" style="margin:0">
      <div class="block-title">Activité (<?= $period ?> derniers jours)</div>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="st-row">
          <span class="st-label">RDV planifiés</span>
          <span class="st-val"><?= $appts_period ?></span>
        </div>
        <div class="st-row">
          <span class="st-label">RDV en attente</span>
          <span class="st-val" style="color:var(--gold)"><?= $appts_pending ?></span>
        </div>
        <div class="st-row">
          <span class="st-label">Messages non lus</span>
          <span class="st-val" style="color:<?= $unread > 0 ? 'var(--red)' : 'var(--soft)' ?>"><?= $unread ?></span>
        </div>
      </div>

      <!-- Mini graphe biens/mois -->
      <?php if (!empty($monthly)): ?>
      <div style="margin-top:20px;">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Biens ajoutés (6 mois)</div>
        <div style="display:flex;align-items:flex-end;gap:6px;height:60px;">
          <?php
          $max_m = max(array_column($monthly, 'count')) ?: 1;
          foreach ($monthly as $m):
            $h = max(4, round(($m['count'] / $max_m) * 56));
            $mo = date('M', strtotime($m['month'] . '-01'));
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
            <div style="width:100%;height:<?= $h ?>px;background:var(--gold);border-radius:4px 4px 0 0;opacity:.8;min-height:4px;"></div>
            <span style="font-size:9px;color:var(--muted)"><?= $mo ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top biens -->
  <div class="block-title">Top 5 biens les plus consultés</div>
  <div class="table-wrap" style="margin-bottom:32px;">
    <table>
      <thead>
        <tr>
          <th>Bien</th>
          <th>Ville</th>
          <th>Prix</th>
          <th>Statut</th>
          <th>Vues</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($top_props as $i => $tp): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:8px;overflow:hidden;background:var(--surf2);flex-shrink:0;">
                <?php if ($tp['img']): ?><img src="../<?= htmlspecialchars($tp['img']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
              </div>
              <span style="color:var(--text);font-weight:500;font-size:13px;"><?= htmlspecialchars($tp['title']) ?></span>
            </div>
          </td>
          <td><?= htmlspecialchars($tp['city']) ?></td>
          <td style="color:var(--gold);font-weight:600;"><?= number_format($tp['price'],0,',',' ') ?> €</td>
          <td><span class="badge badge-<?= $status_colors[$tp['status']] ?? 'gray' ?>"><?= $status_labels[$tp['status']] ?? $tp['status'] ?></span></td>
          <td><strong style="color:var(--text)"><?= $tp['views_count'] ?></strong></td>
          <td><a href="../property_detail.php?id=<?= $tp['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <a href="index.php" class="btn btn-secondary">← Tableau de bord</a>
</div>

<style>
.st-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); }
.st-row:last-child { border-bottom:none; }
.st-label { font-size:13px; color:var(--soft); }
.st-val   { font-family:'Syne',sans-serif; font-size:16px; font-weight:700; color:var(--text); }
</style>

<?php require '../includes/footer.php'; ?>