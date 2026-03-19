<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$conv_id = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;
if (!$conv_id) { header('Location: messages.php'); exit; }

// Vérifier que l'utilisateur est participant
$part = $pdo->prepare("SELECT * FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$part->execute([$conv_id, $user_id]);
if (!$part->fetch()) { header('Location: messages.php'); exit; }

// Marquer comme lu
$pdo->prepare("UPDATE conversation_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?")
    ->execute([$conv_id, $user_id]);

$error = null;

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'])) {
    $body = trim($_POST['body'] ?? '');
    if ($body) {
        try {
            $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)")
                ->execute([$conv_id, $user_id, $body]);
            $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")
                ->execute([$conv_id]);
            header("Location: message_detail.php?conv=$conv_id");
            exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

// Infos de la conversation
$conv = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
$conv->execute([$conv_id]); $conv = $conv->fetch(PDO::FETCH_ASSOC);

// L'autre participant
$other = $pdo->prepare("
    SELECT u.* FROM users u
    JOIN conversation_participants cp ON cp.user_id = u.id
    WHERE cp.conversation_id = ? AND u.id != ?
    LIMIT 1
");
$other->execute([$conv_id, $user_id]); $other = $other->fetch(PDO::FETCH_ASSOC);

// Messages
$msgs = $pdo->prepare("
    SELECT m.*, u.name AS sender_name, u.profile_image AS sender_photo
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$msgs->execute([$conv_id]); $msgs = $msgs->fetchAll(PDO::FETCH_ASSOC);

$role_labels = ['client'=>'Client','agent'=>'Agent','admin'=>'Admin'];
$base = (strpos($_SERVER['PHP_SELF'], '/agent/') !== false) ? '../' : '';
require ($base ? '../' : '') . 'includes/header.php';
?>

<div class="container" style="max-width:820px;">
  <!-- Back -->
  <a href="messages.php" class="ja-back" style="margin-bottom:20px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Retour aux messages
  </a>

  <!-- Header conversation -->
  <div class="conv-header">
    <div class="conv-other-avatar">
      <?php if ($other['profile_image']): ?>
        <img src="<?= $base ?>uploads/<?= htmlspecialchars($other['profile_image']) ?>" alt="">
      <?php else: ?>
        <?= strtoupper(substr($other['name'], 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div>
      <div class="conv-other-name"><?= htmlspecialchars($other['name']) ?></div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="badge badge-gray"><?= $role_labels[$other['role']] ?? $other['role'] ?></span>
        <?php if ($conv['subject']): ?>
          <span style="font-size:12px;color:var(--muted);">· <?= htmlspecialchars($conv['subject']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Messages -->
  <div class="conv-messages" id="conv-messages">
    <?php foreach ($msgs as $msg):
      $is_mine = $msg['sender_id'] == $user_id;
    ?>
      <div class="conv-msg <?= $is_mine ? 'mine' : 'theirs' ?>">
        <?php if (!$is_mine): ?>
          <div class="conv-msg-avatar">
            <?php if ($msg['sender_photo']): ?>
              <img src="<?= $base ?>uploads/<?= htmlspecialchars($msg['sender_photo']) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(substr($msg['sender_name'], 0, 1)) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="conv-msg-bubble">
          <div class="conv-msg-text"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
          <div class="conv-msg-time"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Zone de réponse -->
  <form method="POST" class="conv-reply">
    <textarea name="body" class="conv-reply-input" placeholder="Écrire un message…" rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit()}"
      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
    <button type="submit" class="conv-reply-btn">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</div>

<script>
// Scroll bas automatique
const el = document.getElementById('conv-messages');
if (el) el.scrollTop = el.scrollHeight;
</script>

<?php require ($base ? '../' : '') . 'includes/footer.php'; ?>