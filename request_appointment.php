<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$error = $success = null;

// Créer une demande de RDV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agent_id    = (int)($_POST['agent_id'] ?? 0);
    $property_id = (int)($_POST['property_id'] ?? 0) ?: null;
    $date        = trim($_POST['scheduled_date'] ?? '');
    $time        = trim($_POST['scheduled_time'] ?? '');
    $duration    = (int)($_POST['duration'] ?? 60);
    $note        = trim($_POST['note'] ?? '');

    if (!$agent_id || !$date || !$time) {
        $error = "Agent, date et heure sont requis.";
    } else {
        $scheduled_at = $date . ' ' . $time . ':00';
        if (strtotime($scheduled_at) < time()) {
            $error = "Impossible de réserver un créneau dans le passé.";
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO appointments (property_id, client_id, agent_id, scheduled_at, duration_minutes, note, status)
                    VALUES (?,?,?,?,?,?,'pending')
                ")->execute([$property_id, $user_id, $agent_id, $scheduled_at, $duration, $note ?: null]);
                $success = "Votre demande de rendez-vous a été envoyée ! L'agent vous confirmera le créneau.";
            } catch (PDOException $e) { $error = $e->getMessage(); }
        }
    }
}

// Pré-remplir depuis l'URL (depuis une fiche bien)
$pre_property_id = isset($_GET['property']) ? (int)$_GET['property'] : 0;
$pre_agent_id    = isset($_GET['agent'])    ? (int)$_GET['agent']    : 0;

// Bien pré-sélectionné
$pre_property = null;
if ($pre_property_id) {
    $st = $pdo->prepare("SELECT id, title, city, agent_id FROM properties WHERE id = ? LIMIT 1");
    $st->execute([$pre_property_id]); $pre_property = $st->fetch(PDO::FETCH_ASSOC);
    if ($pre_property && !$pre_agent_id) $pre_agent_id = $pre_property['agent_id'];
}

// Liste des agents
$agents = $pdo->query("SELECT u.id, u.name, u.email, u.phone FROM users u WHERE u.role = 'agent' AND u.is_active = 1 ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<div class="container" style="max-width:640px;">
  <a href="javascript:history.back()" class="ja-back">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Retour
  </a>

  <div class="page-eyebrow">Prise de rendez-vous</div>
  <h1>Demander une visite</h1>

  <?php if ($error):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <?php if ($pre_property): ?>
    <div style="background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
      <svg width="20" height="20" fill="none" stroke="var(--gold)" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:var(--text)"><?= htmlspecialchars($pre_property['title']) ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($pre_property['city']) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="form-section">
    <form method="POST">
      <?php if ($pre_property_id): ?>
        <input type="hidden" name="property_id" value="<?= $pre_property_id ?>">
      <?php else: ?>
      <div class="form-group">
        <label>Bien concerné (optionnel)</label>
        <select name="property_id">
          <option value="">— Aucun bien en particulier —</option>
          <?php
          $all_props = $pdo->query("SELECT id, title, city FROM properties WHERE status = 'available' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
          foreach ($all_props as $ap):
          ?><option value="<?= $ap['id'] ?>"><?= htmlspecialchars($ap['title']) ?> — <?= htmlspecialchars($ap['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label>Agent <span class="req">*</span></label>
        <select name="agent_id" required>
          <option value="">— Sélectionner un agent —</option>
          <?php foreach ($agents as $ag): ?>
            <option value="<?= $ag['id'] ?>" <?= $ag['id'] == $pre_agent_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($ag['name']) ?>
              <?php if ($ag['phone']): ?>· <?= htmlspecialchars($ag['phone']) ?><?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label>Date souhaitée <span class="req">*</span></label>
          <input type="date" name="scheduled_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>
        <div class="form-group">
          <label>Heure souhaitée <span class="req">*</span></label>
          <input type="time" name="scheduled_time" value="10:00" required>
        </div>
      </div>

      <div class="form-group">
        <label>Durée estimée</label>
        <select name="duration">
          <option value="30">30 minutes</option>
          <option value="60" selected>1 heure</option>
          <option value="90">1h30</option>
          <option value="120">2 heures</option>
        </select>
      </div>

      <div class="form-group">
        <label>Message pour l'agent</label>
        <textarea name="note" placeholder="Précisez vos disponibilités, questions…" rows="3"></textarea>
      </div>

      <button type="submit" class="btn" style="width:100%;justify-content:center;padding:13px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Envoyer la demande
      </button>
    </form>
  </div>
</div>

<?php require 'includes/footer.php'; ?>