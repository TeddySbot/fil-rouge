<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$error = $success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: index.php'); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city    = trim($_POST['city'] ?? '');
            if (!$name)                                                    $error = "Le nom est requis.";
            elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Email invalide.";
            elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) $error = "Téléphone invalide.";
            else {
                $pdo->prepare("UPDATE users SET name=:name,email=:email,phone=:phone,address=:address,city=:city WHERE id=:id")
                    ->execute(['name'=>$name,'email'=>$email,'phone'=>$phone?:null,'address'=>$address?:null,'city'=>$city?:null,'id'=>$user_id]);
                $_SESSION['name'] = $name;
                $success = "Profil mis à jour.";
                $stmt->execute(['id'=>$user_id]); $user = $stmt->fetch();
            }
        } elseif ($_POST['action'] === 'update_password') {
            $old=$_POST['old_password']??''; $new=$_POST['new_password']??''; $cfm=$_POST['confirm_password']??'';
            if (!password_verify($old,$user['password']))  $error = "Ancien mot de passe incorrect.";
            elseif (strlen($new)<6)                        $error = "Minimum 6 caractères.";
            elseif ($new!==$cfm)                           $error = "Les mots de passe ne correspondent pas.";
            else {
                $pdo->prepare("UPDATE users SET password=:pw WHERE id=:id")
                    ->execute(['pw'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$user_id]);
                $success = "Mot de passe mis à jour.";
            }
        } elseif ($_POST['action'] === 'remove_favorite') {
            $pdo->prepare("DELETE FROM favorites WHERE user_id=? AND property_id=?")
                ->execute([$user_id,(int)$_POST['property_id']]);
            $success = "Retiré des favoris.";
        }
    }
} catch (PDOException $e) { $error = "Erreur : ".$e->getMessage(); }

$valid_tabs = ['profile','password','favorites','messages','appointments'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'],$valid_tabs) ? $_GET['tab'] : 'profile';

$role_labels   = ['client'=>'Client','agent'=>'Agent','admin'=>'Administrateur','attente'=>'En attente'];
$type_labels   = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];
$status_colors = ['available'=>'badge-green','sold'=>'badge-gold','rented'=>'badge-blue'];
$status_labels = ['available'=>'Disponible','sold'=>'Vendu','rented'=>'Loué'];
$appt_status_labels = ['pending'=>'En attente','confirmed'=>'Confirmé','cancelled'=>'Annulé','done'=>'Effectué'];
$appt_status_colors = ['pending'=>'badge-gold','confirmed'=>'badge-green','cancelled'=>'badge-red','done'=>'badge-gray'];

// Compter favoris
$fav_count = 0;
$fav_stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=?");
$fav_stmt->execute([$user_id]); $fav_count = $fav_stmt->fetchColumn();

// Compter messages non lus
$unread_count = 0;
try {
    $u = $pdo->prepare("SELECT COUNT(*) FROM messages m JOIN conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=? WHERE m.sender_id!=? AND (cp.last_read_at IS NULL OR m.created_at>cp.last_read_at)");
    $u->execute([$user_id,$user_id]); $unread_count = $u->fetchColumn();
} catch(PDOException $e){}

// Compter RDV en attente
$appt_pending = 0;
try {
    $ap = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id=? AND status='pending'");
    $ap->execute([$user_id]); $appt_pending = $ap->fetchColumn();
} catch(PDOException $e){}

// Données onglet favoris
$favs = [];
if ($active_tab === 'favorites') {
    $fs = $pdo->prepare("SELECT p.*,f.created_at AS fav_at,u.name AS agent_name,u.id AS agent_id,(SELECT image_path FROM property_images WHERE property_id=p.id AND is_main=1 LIMIT 1) AS main_img FROM favorites f JOIN properties p ON p.id=f.property_id LEFT JOIN users u ON u.id=p.agent_id WHERE f.user_id=? ORDER BY f.created_at DESC");
    $fs->execute([$user_id]); $favs = $fs->fetchAll(PDO::FETCH_ASSOC);
}

// Données onglet messages
$conversations = [];
if ($active_tab === 'messages') {
    try {
        $cs = $pdo->prepare("SELECT c.*,m_last.body AS last_body,m_last.created_at AS last_at,m_last.sender_id AS last_sender,u_o.id AS other_id,u_o.name AS other_name,u_o.profile_image AS other_photo,u_o.role AS other_role,(SELECT COUNT(*) FROM messages m2 JOIN conversation_participants cp2 ON cp2.conversation_id=m2.conversation_id AND cp2.user_id=? WHERE m2.conversation_id=c.id AND m2.sender_id!=? AND (cp2.last_read_at IS NULL OR m2.created_at>cp2.last_read_at)) AS unread FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=? JOIN conversation_participants cp_o ON cp_o.conversation_id=c.id AND cp_o.user_id!=? JOIN users u_o ON u_o.id=cp_o.user_id LEFT JOIN messages m_last ON m_last.id=(SELECT id FROM messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1) ORDER BY c.updated_at DESC");
        $cs->execute([$user_id,$user_id,$user_id,$user_id]); $conversations = $cs->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e){}
}

// Données onglet rendez-vous
$appointments = [];
if ($active_tab === 'appointments') {
    try {
        $as = $pdo->prepare("SELECT a.*,u.name AS agent_name,u.phone AS agent_phone,u.email AS agent_email,p.title AS property_title,p.id AS property_id,p.city AS property_city FROM appointments a JOIN users u ON u.id=a.agent_id LEFT JOIN properties p ON p.id=a.property_id WHERE a.client_id=? ORDER BY a.scheduled_at DESC");
        $as->execute([$user_id]); $appointments = $as->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e){}
}

require 'includes/header.php';
?>

<div class="container">
  <?php if ($error):   ?><div class="error"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="account-layout">

    <!-- Sidebar -->
    <aside class="account-sidebar">
      <div class="account-avatar-wrap">
        <div class="account-avatar">
          <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.png'): ?>
            <img src="uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="">
          <?php else: ?>
            <?= strtoupper(substr($user['name'],0,1)) ?>
          <?php endif; ?>
        </div>
        <div class="account-name"><?= htmlspecialchars($user['name']) ?></div>
        <span class="account-role"><?= $role_labels[$user['role']] ?? $user['role'] ?></span>
      </div>
      <nav class="account-nav">
        <a href="?tab=profile" class="<?= $active_tab==='profile'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Mon profil
        </a>
        <a href="?tab=password" class="<?= $active_tab==='password'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Mot de passe
        </a>
        <?php if (in_array($user['role'],['client','attente'])): ?>
        <a href="?tab=favorites" class="<?= $active_tab==='favorites'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          Mes favoris
          <?php if ($fav_count>0): ?><span class="acc-nav-badge"><?= $fav_count ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="?tab=messages" class="<?= $active_tab==='messages'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Messages
          <?php if ($unread_count>0): ?><span class="acc-nav-badge acc-nav-badge-alert"><?= $unread_count ?></span><?php endif; ?>
        </a>
        <?php if (in_array($user['role'],['client','attente'])): ?>
        <a href="?tab=appointments" class="<?= $active_tab==='appointments'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Rendez-vous
          <?php if ($appt_pending>0): ?><span class="acc-nav-badge acc-nav-badge-alert"><?= $appt_pending ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if ($user['role']==='agent'): ?>
        <a href="agent/index.php">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          Espace agent
        </a>
        <?php endif; ?>
        <a href="logout.php" style="color:var(--red);opacity:.8">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Déconnexion
        </a>
      </nav>
    </aside>

    <!-- Contenu -->
    <div>

      <?php if ($active_tab==='profile'): ?>
      <div class="form-section" style="margin-bottom:20px;">
        <div class="block-title">Informations actuelles</div>
        <div class="account-info-grid">
          <div class="account-info-item"><div class="account-info-label">Statut</div><div class="account-info-value"><span class="badge <?= $user['is_active']?'badge-green':'badge-red' ?>">● <?= $user['is_active']?'Actif':'Inactif' ?></span></div></div>
          <div class="account-info-item"><div class="account-info-label">Membre depuis</div><div class="account-info-value"><?= date('d/m/Y',strtotime($user['created_at'])) ?></div></div>
          <div class="account-info-item"><div class="account-info-label">Téléphone</div><div class="account-info-value"><?= htmlspecialchars($user['phone']??'—') ?></div></div>
          <div class="account-info-item"><div class="account-info-label">Ville</div><div class="account-info-value"><?= htmlspecialchars($user['city']??'—') ?></div></div>
        </div>
      </div>
      <div class="form-section">
        <div class="block-title">Modifier mon profil</div>
        <form method="post">
          <input type="hidden" name="action" value="update_profile">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
            <div class="form-group" style="grid-column:span 2"><label>Nom complet <span class="req">*</span></label><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
            <div class="form-group" style="grid-column:span 2"><label>Email <span class="req">*</span></label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
            <div class="form-group"><label>Téléphone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>"></div>
            <div class="form-group"><label>Ville</label><input type="text" name="city" value="<?= htmlspecialchars($user['city']??'') ?>"></div>
            <div class="form-group" style="grid-column:span 2"><label>Adresse</label><input type="text" name="address" value="<?= htmlspecialchars($user['address']??'') ?>"></div>
          </div>
          <button type="submit" class="btn">Enregistrer</button>
        </form>
      </div>

      <?php elseif ($active_tab==='password'): ?>
      <div class="form-section">
        <div class="block-title">Changer le mot de passe</div>
        <form method="post" style="max-width:400px;">
          <input type="hidden" name="action" value="update_password">
          <div class="form-group"><label>Mot de passe actuel <span class="req">*</span></label><input type="password" name="old_password" placeholder="••••••••" required></div>
          <div class="form-group"><label>Nouveau mot de passe <span class="req">*</span></label><input type="password" name="new_password" placeholder="Min. 6 caractères" required></div>
          <div class="form-group"><label>Confirmer <span class="req">*</span></label><input type="password" name="confirm_password" placeholder="Répétez" required></div>
          <button type="submit" class="btn">Mettre à jour</button>
        </form>
      </div>

      <?php elseif ($active_tab==='favorites'): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0">Mes favoris <span style="font-size:14px;color:var(--muted);font-weight:400">(<?= count($favs) ?>)</span></h2>
        <?php if (count($favs)>=2): ?>
          <a href="compare.php?ids=<?= implode(',',array_slice(array_column($favs,'id'),0,3)) ?>" class="btn btn-secondary btn-sm">⚖️ Comparer</a>
        <?php endif; ?>
      </div>
      <?php if (empty($favs)): ?>
        <div class="form-section" style="text-align:center;padding:48px 24px;">
          <div style="font-size:36px;margin-bottom:12px;">🤍</div>
          <p style="margin-bottom:16px;">Aucun bien en favoris.</p>
          <a href="properties.php" class="btn">Parcourir les biens</a>
        </div>
      <?php else: ?>
        <div class="properties-grid" style="grid-template-columns:repeat(2,1fr);">
          <?php foreach ($favs as $p): ?>
          <div class="property-card">
            <div class="property-card-img" style="height:160px;">
              <?php if ($p['main_img']): ?><img src="<?= htmlspecialchars($p['main_img']) ?>" alt=""><?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--border);"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
              <?php endif; ?>
              <div style="position:absolute;top:8px;left:8px;"><span class="badge <?= $status_colors[$p['status']]??'badge-gray' ?>"><?= $status_labels[$p['status']] ?></span></div>
            </div>
            <div class="property-card-body">
              <div class="property-card-price"><?= number_format($p['price'],0,',',' ') ?> €</div>
              <div class="property-card-title"><?= htmlspecialchars($p['title']) ?></div>
              <div class="property-card-city">📍 <?= htmlspecialchars($p['city']) ?> · <?= $type_labels[$p['property_type']]??$p['property_type'] ?></div>
              <div class="property-card-meta">
                <?php if($p['surface']): ?><span>⬛ <?= $p['surface'] ?> m²</span><?php endif; ?>
                <?php if($p['rooms']): ?><span>🚪 <?= $p['rooms'] ?></span><?php endif; ?>
              </div>
            </div>
            <div style="padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:6px;">
              <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">Voir</a>
              <a href="request_appointment.php?property=<?= $p['id'] ?>&agent=<?= $p['agent_id'] ?>" class="btn btn-sm" style="flex:1;justify-content:center;">📅 RDV</a>
              <form method="POST" style="display:contents">
                <input type="hidden" name="action" value="remove_favorite">
                <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Retirer des favoris ?')" title="Retirer">♥</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php elseif ($active_tab==='messages'): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0">Messages <?php if($unread_count>0): ?><span class="acc-nav-badge acc-nav-badge-alert" style="display:inline-flex;"><?= $unread_count ?></span><?php endif; ?></h2>
        <a href="messages.php" class="btn btn-sm">Messagerie complète</a>
      </div>
      <?php if (empty($conversations)): ?>
        <div class="form-section" style="text-align:center;padding:48px 24px;">
          <div style="font-size:36px;margin-bottom:12px;">💬</div>
          <p style="margin-bottom:16px;">Aucune conversation.</p>
          <a href="messages.php" class="btn">Écrire un message</a>
        </div>
      <?php else: ?>
        <div class="msg-list">
          <?php foreach ($conversations as $conv): ?>
          <a href="message_detail.php?conv=<?= $conv['id'] ?>" class="msg-item <?= $conv['unread']>0?'unread':'' ?>">
            <div class="msg-avatar">
              <?php if ($conv['other_photo']): ?><img src="uploads/<?= htmlspecialchars($conv['other_photo']) ?>" alt=""><?php else: ?><?= strtoupper(substr($conv['other_name'],0,1)) ?><?php endif; ?>
              <?php if ($conv['unread']>0): ?><span class="msg-unread-dot"><?= $conv['unread'] ?></span><?php endif; ?>
            </div>
            <div class="msg-item-body">
              <div class="msg-item-top">
                <span class="msg-item-name"><?= htmlspecialchars($conv['other_name']) ?></span>
                <span class="msg-item-time"><?= $conv['last_at']?date('d/m H:i',strtotime($conv['last_at'])):'' ?></span>
              </div>
              <?php if ($conv['subject']): ?><div class="msg-item-subject"><?= htmlspecialchars($conv['subject']) ?></div><?php endif; ?>
              <div class="msg-item-preview">
                <?= $conv['last_sender']==$user_id?'<span style="color:var(--muted)">Vous : </span>':'' ?>
                <?= htmlspecialchars(mb_strimwidth($conv['last_body']??'',0,70,'…')) ?>
              </div>
            </div>
            <span class="badge badge-gray" style="flex-shrink:0"><?= $role_labels[$conv['other_role']]??$conv['other_role'] ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php elseif ($active_tab==='appointments'): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0">Mes rendez-vous</h2>
        <a href="request_appointment.php" class="btn btn-sm">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nouveau RDV
        </a>
      </div>
      <?php if (empty($appointments)): ?>
        <div class="form-section" style="text-align:center;padding:48px 24px;">
          <div style="font-size:36px;margin-bottom:12px;">📅</div>
          <p style="margin-bottom:16px;">Aucun rendez-vous pour l'instant.</p>
          <a href="properties.php" class="btn btn-secondary">Trouver un bien</a>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($appointments as $appt):
            $dt=new DateTime($appt['scheduled_at']); $is_past=$dt<new DateTime();
            $sc=$appt_status_colors[$appt['status']]??'badge-gray';
            $sl=$appt_status_labels[$appt['status']]??$appt['status'];
          ?>
          <div class="appt-card" style="<?= $is_past?'opacity:.7':'' ?>">
            <div class="appt-date">
              <div class="appt-day"><?= $dt->format('d') ?></div>
              <div class="appt-month"><?= strftime('%b',$dt->getTimestamp()) ?></div>
              <div class="appt-time"><?= $dt->format('H:i') ?></div>
            </div>
            <div class="appt-body">
              <div class="appt-client">
                <div>
                  <div class="appt-client-name">Agent : <?= htmlspecialchars($appt['agent_name']) ?></div>
                  <div class="appt-client-meta">
                    <?php if ($appt['agent_phone']): ?><a href="tel:<?= htmlspecialchars($appt['agent_phone']) ?>"><?= htmlspecialchars($appt['agent_phone']) ?></a> · <?php endif; ?>
                    <a href="mailto:<?= htmlspecialchars($appt['agent_email']) ?>"><?= htmlspecialchars($appt['agent_email']) ?></a>
                  </div>
                </div>
              </div>
              <?php if ($appt['property_title']): ?>
              <div class="appt-property">
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                <a href="property_detail.php?id=<?= $appt['property_id'] ?>"><?= htmlspecialchars($appt['property_title']) ?></a>
                <?php if ($appt['property_city']): ?>· <?= htmlspecialchars($appt['property_city']) ?><?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if ($appt['note']): ?><div class="appt-note">"<?= htmlspecialchars($appt['note']) ?>"</div><?php endif; ?>
            </div>
            <div class="appt-side">
              <span class="badge <?= $sc ?>"><?= $sl ?></span>
              <span style="font-size:11px;color:var(--muted)"><?= $appt['duration_minutes'] ?> min</span>
              <?php if ($appt['status']==='confirmed' && !$is_past): ?>
                <span style="font-size:11px;color:var(--green)">✓ Confirmé</span>
              <?php elseif ($appt['status']==='pending'): ?>
                <span style="font-size:11px;color:var(--muted)">En attente</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.acc-nav-badge {
  margin-left:auto; min-width:18px; height:18px; padding:0 5px;
  background:var(--surf2); border:1px solid var(--border); color:var(--soft);
  font-size:10px; font-weight:700; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
}
.acc-nav-badge-alert { background:var(--gold); border-color:var(--gold); color:#0C0C0E; }
</style>

<?php require 'includes/footer.php'; ?>