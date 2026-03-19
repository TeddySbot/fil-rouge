<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php'); exit;
}

$agent_id = $_SESSION['user_id'];
$error = $success = null;

// Confirmer / annuler / marquer done un RDV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'], $_POST['action'])) {
    $appt_id = (int)$_POST['appt_id'];
    $action  = $_POST['action'];
    $allowed = ['confirmed','cancelled','done'];
    if (in_array($action, $allowed)) {
        try {
            $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND agent_id = ?")
                ->execute([$action, $appt_id, $agent_id]);
            $success = match($action) {
                'confirmed' => "Rendez-vous confirmé.",
                'cancelled' => "Rendez-vous annulé.",
                'done'      => "Rendez-vous marqué comme effectué.",
            };
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

// Vue : semaine courante ou filtre
$view  = $_GET['view'] ?? 'upcoming';
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['pending','confirmed','cancelled','done']) ? $_GET['status'] : '';

$where  = ["a.agent_id = ?"];
$params = [$agent_id];

if ($view === 'upcoming') {
    $where[] = "a.scheduled_at >= NOW()";
    $where[] = "a.status IN ('pending','confirmed')";
} elseif ($view === 'past') {
    $where[] = "a.scheduled_at < NOW()";
}
if ($filter_status) { $where[] = "a.status = ?"; $params[] = $filter_status; }

$appts = $pdo->prepare("
    SELECT a.*,
           u.name AS client_name, u.email AS client_email, u.phone AS client_phone, u.profile_image AS client_photo,
           p.title AS property_title, p.city AS property_city, p.id AS property_id,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS property_img
    FROM appointments a
    JOIN users u ON u.id = a.client_id
    LEFT JOIN properties p ON p.id = a.property_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.scheduled_at ASC
");
$appts->execute($params);
$appts = $appts->fetchAll(PDO::FETCH_ASSOC);

// Comptes par statut
$counts = $pdo->prepare("SELECT status, COUNT(*) AS c FROM appointments WHERE agent_id = ? GROUP BY status");
$counts->execute([$agent_id]);
$counts = array_column($counts->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');

$status_labels = ['pending'=>'En attente','confirmed'=>'Confirmé','cancelled'=>'Annulé','done'=>'Effectué'];
$status_colors = ['pending'=>'gold','confirmed'=>'green','cancelled'=>'red','done'=>'gray'];

require '../includes/header.php';
?>

<div class="container">
  <div class="dash-header">
    <div>
      <div class="page-eyebrow">Espace agent</div>
      <h1 style="margin-bottom:0">Agenda</h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?php if ($counts['pending'] ?? 0): ?>
        <span class="badge badge-gold">● <?= $counts['pending'] ?> en attente</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Filtres vue -->
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="?view=upcoming" class="btn btn-sm <?= $view==='upcoming'?'':'btn-secondary' ?>">À venir</a>
    <a href="?view=past"     class="btn btn-sm <?= $view==='past'?'':'btn-secondary' ?>">Passés</a>
    <a href="?view=all"      class="btn btn-sm <?= $view==='all'?'':'btn-secondary' ?>">Tous</a>
    <div style="flex:1"></div>
    <?php foreach ($status_labels as $k => $v): ?>
      <a href="?view=<?= $view ?>&status=<?= $k ?>" class="btn btn-sm btn-secondary <?= $filter_status===$k?'':'opacity-60' ?>" style="opacity:<?= $filter_status===$k?1:.6 ?>">
        <?= $v ?> (<?= $counts[$k] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
    <?php if ($filter_status): ?><a href="?view=<?= $view ?>" class="btn btn-secondary btn-sm">✕</a><?php endif; ?>
  </div>

  <?php if (empty($appts)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--muted);">
      <div style="font-size:36px;margin-bottom:12px;">📅</div>
      <p>Aucun rendez-vous<?= $view==='upcoming'?' à venir':'' ?>.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:32px;">
      <?php foreach ($appts as $appt):
        $sc = $status_colors[$appt['status']] ?? 'gray';
        $sl = $status_labels[$appt['status']] ?? $appt['status'];
        $dt = new DateTime($appt['scheduled_at']);
        $is_past = $dt < new DateTime();
      ?>
      <div class="appt-card">
        <!-- Date/heure -->
        <div class="appt-date">
          <div class="appt-day"><?= $dt->format('d') ?></div>
          <div class="appt-month"><?= strftime('%b', $dt->getTimestamp()) ?></div>
          <div class="appt-time"><?= $dt->format('H:i') ?></div>
        </div>

        <!-- Infos -->
        <div class="appt-body">
          <!-- Client -->
          <div class="appt-client">
            <div class="appt-avatar">
              <?php if ($appt['client_photo']): ?>
                <img src="../<?= htmlspecialchars($appt['client_photo']) ?>" alt="">
              <?php else: ?>
                <?= strtoupper(substr($appt['client_name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div>
              <div class="appt-client-name"><?= htmlspecialchars($appt['client_name']) ?></div>
              <div class="appt-client-meta">
                <?php if ($appt['client_phone']): ?>
                  <a href="tel:<?= htmlspecialchars($appt['client_phone']) ?>"><?= htmlspecialchars($appt['client_phone']) ?></a> ·
                <?php endif; ?>
                <a href="mailto:<?= htmlspecialchars($appt['client_email']) ?>"><?= htmlspecialchars($appt['client_email']) ?></a>
              </div>
            </div>
          </div>

          <!-- Bien concerné -->
          <?php if ($appt['property_title']): ?>
          <div class="appt-property">
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <a href="../property_detail.php?id=<?= $appt['property_id'] ?>"><?= htmlspecialchars($appt['property_title']) ?></a>
            <?php if ($appt['property_city']): ?>· <?= htmlspecialchars($appt['property_city']) ?><?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Note -->
          <?php if ($appt['note']): ?>
            <div class="appt-note">"<?= htmlspecialchars($appt['note']) ?>"</div>
          <?php endif; ?>
        </div>

        <!-- Statut + actions -->
        <div class="appt-side">
          <span class="badge badge-<?= $sc ?>"><?= $sl ?></span>
          <div class="appt-duration"><?= $appt['duration_minutes'] ?> min</div>

          <?php if (!$is_past && $appt['status'] === 'pending'): ?>
          <div class="appt-actions">
            <form method="POST" style="display:contents">
              <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
              <input type="hidden" name="action" value="confirmed">
              <button type="submit" class="btn btn-success btn-sm">✓ Confirmer</button>
            </form>
            <form method="POST" style="display:contents">
              <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
              <input type="hidden" name="action" value="cancelled">
              <button type="submit" class="btn btn-danger btn-sm">✕ Refuser</button>
            </form>
          </div>
          <?php elseif ($appt['status'] === 'confirmed' && !$is_past): ?>
          <div class="appt-actions">
            <form method="POST" style="display:contents">
              <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
              <input type="hidden" name="action" value="done">
              <button type="submit" class="btn btn-sm btn-secondary">Marquer effectué</button>
            </form>
            <form method="POST" style="display:contents">
              <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
              <input type="hidden" name="action" value="cancelled">
              <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <a href="index.php" class="btn btn-secondary">← Tableau de bord</a>
</div>

<?php require '../includes/footer.php'; ?>