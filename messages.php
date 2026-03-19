<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$error = $success = null;

// Créer une nouvelle conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_conversation') {
    $to_id  = (int)($_POST['to_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if (!$to_id || !$body) {
        $error = "Destinataire et message requis.";
    } else {
        try {
            $pdo->beginTransaction();
            // Créer la conversation
            $pdo->prepare("INSERT INTO conversations (subject) VALUES (?)")->execute([$subject ?: null]);
            $conv_id = $pdo->lastInsertId();
            // Ajouter les participants
            $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?,?)")->execute([$conv_id, $user_id]);
            $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?,?)")->execute([$conv_id, $to_id]);
            // Premier message
            $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)")->execute([$conv_id, $user_id, $body]);
            $pdo->commit();
            header("Location: messages.php?conv=$conv_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Liste des conversations
$conversations = $pdo->prepare("
    SELECT c.*,
           m_last.body AS last_body, m_last.created_at AS last_at, m_last.sender_id AS last_sender_id,
           u_other.id AS other_id, u_other.name AS other_name, u_other.profile_image AS other_photo, u_other.role AS other_role,
           (SELECT COUNT(*) FROM messages m2
            JOIN conversation_participants cp2 ON cp2.conversation_id = m2.conversation_id AND cp2.user_id = ?
            WHERE m2.conversation_id = c.id AND m2.sender_id != ?
            AND (cp2.last_read_at IS NULL OR m2.created_at > cp2.last_read_at)) AS unread_count
    FROM conversations c
    JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
    JOIN conversation_participants cp_other ON cp_other.conversation_id = c.id AND cp_other.user_id != ?
    JOIN users u_other ON u_other.id = cp_other.user_id
    LEFT JOIN messages m_last ON m_last.id = (
        SELECT id FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1
    )
    ORDER BY c.updated_at DESC
");
$conversations->execute([$user_id, $user_id, $user_id, $user_id]);
$conversations = $conversations->fetchAll(PDO::FETCH_ASSOC);

// Utilisateurs disponibles pour nouvelle conversation (agents si client, clients si agent)
$role = $_SESSION['role'];
if ($role === 'agent') {
    $contacts = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('client','admin') AND is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $contacts = $pdo->query("SELECT id, name, role FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$role_labels = ['client'=>'Client','agent'=>'Agent','admin'=>'Admin','attente'=>'En attente'];

$base = (strpos($_SERVER['PHP_SELF'], '/agent/') !== false) ? '../' : '';
require ($base ? '../' : '') . 'includes/header.php';
?>

<div class="container">
  <div class="dash-header">
    <div>
      <div class="page-eyebrow">Messagerie</div>
      <h1 style="margin-bottom:0">Mes conversations</h1>
    </div>
    <button onclick="document.getElementById('new-conv-modal').classList.toggle('open')" class="btn">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nouveau message
    </button>
  </div>

  <?php if ($error):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Modal nouvelle conversation -->
  <div id="new-conv-modal" class="msg-modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="msg-modal">
      <div class="msg-modal-head">
        <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--text)">Nouveau message</span>
        <button onclick="document.getElementById('new-conv-modal').classList.remove('open')" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:20px;">×</button>
      </div>
      <form method="POST" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
        <input type="hidden" name="action" value="new_conversation">
        <div class="form-group" style="margin:0">
          <label>Destinataire <span class="req">*</span></label>
          <select name="to_id" required>
            <option value="">— Sélectionner —</option>
            <?php foreach ($contacts as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $role_labels[$c['role']] ?? $c['role'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label>Sujet</label>
          <input type="text" name="subject" placeholder="Objet du message…">
        </div>
        <div class="form-group" style="margin:0">
          <label>Message <span class="req">*</span></label>
          <textarea name="body" rows="4" placeholder="Écrivez votre message…" required></textarea>
        </div>
        <button type="submit" class="btn" style="align-self:flex-end;">Envoyer</button>
      </form>
    </div>
  </div>

  <!-- Liste conversations -->
  <?php if (empty($conversations)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--muted);">
      <div style="font-size:36px;margin-bottom:12px;">💬</div>
      <p>Aucune conversation pour le moment.</p>
      <button onclick="document.getElementById('new-conv-modal').classList.add('open')" class="btn btn-secondary">Démarrer une conversation</button>
    </div>
  <?php else: ?>
    <div class="msg-list">
      <?php foreach ($conversations as $conv): ?>
        <a href="message_detail.php?conv=<?= $conv['id'] ?>" class="msg-item <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">
          <div class="msg-avatar">
            <?php if ($conv['other_photo']): ?>
              <img src="<?= $base ?>uploads/<?= htmlspecialchars($conv['other_photo']) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(substr($conv['other_name'], 0, 1)) ?>
            <?php endif; ?>
            <?php if ($conv['unread_count'] > 0): ?>
              <span class="msg-unread-dot"><?= $conv['unread_count'] ?></span>
            <?php endif; ?>
          </div>
          <div class="msg-item-body">
            <div class="msg-item-top">
              <span class="msg-item-name"><?= htmlspecialchars($conv['other_name']) ?></span>
              <span class="msg-item-time"><?= $conv['last_at'] ? date('d/m H:i', strtotime($conv['last_at'])) : '' ?></span>
            </div>
            <?php if ($conv['subject']): ?>
              <div class="msg-item-subject"><?= htmlspecialchars($conv['subject']) ?></div>
            <?php endif; ?>
            <div class="msg-item-preview">
              <?= $conv['last_sender_id'] == $user_id ? '<span style="color:var(--muted)">Vous : </span>' : '' ?>
              <?= htmlspecialchars(mb_strimwidth($conv['last_body'] ?? '', 0, 80, '…')) ?>
            </div>
          </div>
          <div class="msg-item-role">
            <span class="badge badge-gray"><?= $role_labels[$conv['other_role']] ?? $conv['other_role'] ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
$back = $role === 'agent' ? 'agent/index.php' : 'account.php';
?>
<div style="max-width:1100px;margin:0 auto;padding:0 24px 40px;">
  <a href="<?= $base . ($role === 'agent' ? 'index.php' : 'account.php') ?>" class="btn btn-secondary">← Retour</a>
</div>

<?php require ($base ? '../' : '') . 'includes/footer.php'; ?>