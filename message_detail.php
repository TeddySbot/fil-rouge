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
      <?php $otherInitial = strtoupper(mb_substr($other['name'], 0, 1)); ?>
      <?php if ($other['profile_image'] && $other['profile_image'] !== 'default.png'): ?>
        <img src="<?= $base ?>uploads/<?= htmlspecialchars($other['profile_image']) ?>" alt="" onerror="this.outerHTML='<?= $otherInitial ?>'">
      <?php else: ?>
        <?= $otherInitial ?>
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
      <div class="conv-msg <?= $is_mine ? 'mine' : 'theirs' ?>" data-id="<?= (int)$msg['id'] ?>">
        <?php if (!$is_mine): ?>
          <div class="conv-msg-avatar">
            <?php $senderInitial = strtoupper(mb_substr($msg['sender_name'], 0, 1)); ?>
            <?php if ($msg['sender_photo'] && $msg['sender_photo'] !== 'default.png'): ?>
              <img src="<?= $base ?>uploads/<?= htmlspecialchars($msg['sender_photo']) ?>" alt="" onerror="this.outerHTML='<?= $senderInitial ?>'">
            <?php else: ?>
              <?= $senderInitial ?>
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
  <form method="POST" class="conv-reply" id="conv-reply-form">
    <textarea name="body" class="conv-reply-input" placeholder="Écrire un message…" rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.requestSubmit()}"
      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
    <button type="submit" class="conv-reply-btn">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</div>

<script>
(function () {
  const box     = document.getElementById('conv-messages');
  const form    = document.getElementById('conv-reply-form');
  const input   = form.querySelector('.conv-reply-input');
  const convId  = <?= (int)$conv_id ?>;
  const myId    = <?= (int)$user_id ?>;
  const base    = <?= json_encode($base) ?>;
  let   lastId  = <?= $msgs ? (int)end($msgs)['id'] : 0 ?>;
  let   busy    = false;

  const scrollToBottom = () => { box.scrollTop = box.scrollHeight; };
  scrollToBottom();

  const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

  function avatar(msg) {
    const initial = (msg.sender_name || '?').trim().charAt(0).toUpperCase();
    if (msg.sender_photo && msg.sender_photo !== 'default.png') {
      return '<div class="conv-msg-avatar"><img src="' + base + 'uploads/' + esc(msg.sender_photo) +
             '" alt="" onerror="this.outerHTML=\'' + esc(initial) + '\'"></div>';
    }
    return '<div class="conv-msg-avatar">' + esc(initial) + '</div>';
  }

  function render(msg) {
    if (document.querySelector('.conv-msg[data-id="' + msg.id + '"]')) return;
    const mine = msg.is_mine || msg.sender_id === myId;
    const wrap = document.createElement('div');
    wrap.className = 'conv-msg ' + (mine ? 'mine' : 'theirs');
    wrap.dataset.id = msg.id;
    const body = esc(msg.body).replace(/\n/g, '<br>');
    wrap.innerHTML =
      (mine ? '' : avatar(msg)) +
      '<div class="conv-msg-bubble">' +
        '<div class="conv-msg-text">' + body + '</div>' +
        '<div class="conv-msg-time">' + esc(msg.time_fmt || '') + '</div>' +
      '</div>';
    box.appendChild(wrap);
    if (msg.id > lastId) lastId = msg.id;
  }

  async function poll() {
    try {
      const r = await fetch(base + 'messages_poll.php?conv=' + convId + '&last_id=' + lastId, { credentials: 'same-origin' });
      const data = await r.json();
      if (data.messages && data.messages.length) {
        const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        data.messages.forEach(render);
        if (atBottom) scrollToBottom();
      }
    } catch (e) { /* réseau : on réessaiera */ }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = input.value.trim();
    if (!body || busy) return;
    busy = true;
    const fd = new FormData();
    fd.append('conv_id', convId);
    fd.append('body', body);
    try {
      const r = await fetch(base + 'messages_send.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await r.json();
      if (data.success) {
        input.value = '';
        input.style.height = 'auto';
        render({ id: data.id, sender_id: myId, is_mine: true, body: body,
                 time_fmt: new Intl.DateTimeFormat('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }).format(new Date()).replace(',', '') });
        scrollToBottom();
      } else {
        alert(data.error || "Échec de l'envoi");
      }
    } catch (e) {
      alert("Erreur réseau, message non envoyé");
    } finally {
      busy = false;
      input.focus();
    }
  });

  setInterval(poll, 3000);
})();
</script>

<?php require ($base ? '../' : '') . 'includes/footer.php'; ?>