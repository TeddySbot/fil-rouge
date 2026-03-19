<?php
session_start();
require 'config/database.php';

$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$property_id) {
    header('Location: properties.php');
    exit;
}

// Rôle de l'utilisateur connecté
$is_agent = isset($_SESSION['user_id']) && $_SESSION['role'] === 'agent';
$is_admin  = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
$agent_agency_id = null;

if ($is_agent) {
    $stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $agent_agency_id = $row['agency_id'] ?? null;
}

// Récupérer la propriété (visible par tous)
$stmt = $pdo->prepare("
    SELECT p.*,
           u.name AS agent_name, u.email AS agent_email, u.phone AS agent_phone,
           u.profile_image AS agent_photo, u.city AS agent_city,
           a.name AS agency_name, a.logo AS agency_logo, a.id AS agency_id
    FROM properties p
    LEFT JOIN users u    ON u.id = p.agent_id
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$property_id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header('Location: properties.php');
    exit;
}

// Incrémenter les vues
$pdo->prepare("UPDATE properties SET views_count = views_count + 1 WHERE id = ?")->execute([$property_id]);

// Droits de modification : admin OU agent de la même agence
$can_edit = $is_admin || ($is_agent && $agent_agency_id && $agent_agency_id == $p['agency_id']);

// Images
$img_stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_main DESC, id ASC");
$img_stmt->execute([$property_id]);
$images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

// Features JSON
$features = [];
if ($p['features']) {
    $decoded = json_decode($p['features'], true);
    if (is_array($decoded)) $features = $decoded;
}

$type_labels   = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];
$status_labels = ['available'=>'Disponible','sold'=>'Vendu','rented'=>'Loué'];
$status_colors = ['available'=>'green','sold'=>'gold','rented'=>'blue'];
$sc = $status_colors[$p['status']] ?? 'soft';

$back_url = $can_edit ? 'agent/properties.php' : 'properties.php';

$is_fav = false;
if (isset($_SESSION['user_id'])) {
    $fav_check = $pdo->prepare("SELECT id FROM favorites WHERE user_id=? AND property_id=?");
    $fav_check->execute([$_SESSION['user_id'], $property_id]);
    $is_fav = (bool)$fav_check->fetch();
}

require 'includes/header.php';

?>


<div class="pd-page">
<div class="pd-wrap">

  <!-- Topbar -->
  <div class="pd-topbar">
    <a href="<?= $back_url ?>" class="pd-back">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Retour aux propriétés
    </a>

    <?php if ($can_edit): ?>
    <div class="pd-topbar-actions">
      <a href="agent/property_edit.php?id=<?= $p['id'] ?>" class="btn-edit">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modifier
      </a>
      <a href="agent/property_delete.php?id=<?= $p['id'] ?>" class="btn-del">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Supprimer
      </a>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['user_id']) && !$can_edit): ?>
<form method="POST" action="favorites.php">
    <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
    <input type="hidden" name="redirect" value="property_detail.php?id=<?= $p['id'] ?>">
    <button type="submit" class="fav-btn <?= $is_fav ? 'active' : '' ?>" style="width:100%;justify-content:center;">
        <?= $is_fav ? '♥ Retirer des favoris' : '♡ Ajouter aux favoris' ?>
    </button>
</form>
<?php endif; ?>
  </div>
  

  <!-- Gallery -->
  <?php if (!empty($images)): ?>
    <div class="pd-gallery">
      <div class="pd-gallery-main" onclick="openLightbox(0)">
        <img src="<?= htmlspecialchars($images[0]['image_path']) ?>" alt="<?= htmlspecialchars($images[0]['alt_text'] ?? $p['title']) ?>">
      </div>
      <?php for ($i = 1; $i <= 2 && isset($images[$i]); $i++):
        $is_last = ($i === 2 && count($images) > 3);
      ?>
        <div class="pd-gallery-thumb <?= $is_last ? 'more-overlay' : '' ?>"
             data-more="<?= $is_last ? '+' . (count($images) - 3) : '' ?>"
             onclick="openLightbox(<?= $i ?>)">
          <img src="<?= htmlspecialchars($images[$i]['image_path']) ?>" alt="">
        </div>
      <?php endfor; ?>
    </div>
  <?php else: ?>
    <div class="pd-no-gallery">
      <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      <span>Aucune photo disponible</span>
    </div>
  <?php endif; ?>

  <!-- Body -->
  <div class="pd-layout">

    <div class="pd-left">

      <!-- Header -->
      <div class="pd-header-block">
        <div class="pd-badges">
          <span class="pd-badge <?= $sc ?>"><?= $status_labels[$p['status']] ?? $p['status'] ?></span>
          <span class="pd-badge type"><?= $type_labels[$p['property_type']] ?? $p['property_type'] ?></span>
          <?php if ($p['is_featured']): ?><span class="pd-badge feat">★ Mis en avant</span><?php endif; ?>
        </div>
        <h1 class="pd-prop-title"><?= htmlspecialchars($p['title']) ?></h1>
        <div class="pd-prop-city">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
          <?= htmlspecialchars(($p['address'] ? $p['address'] . ', ' : '') . $p['city'] . ($p['postal_code'] ? ' ' . $p['postal_code'] : '')) ?>
        </div>
        <div class="pd-price-row">
          <div class="pd-price"><?= number_format($p['price'], 0, ',', ' ') ?> €</div>
          <?php if ($p['surface'] > 0): ?>
            <div class="pd-price-m2"><?= number_format($p['price'] / $p['surface'], 0, ',', ' ') ?> €/m²</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats -->
      <div class="pd-stats-row">
        <div class="pd-stat"><div class="pd-stat-val"><?= $p['surface'] ?></div><div class="pd-stat-lbl">m²</div></div>
        <div class="pd-stat"><div class="pd-stat-val"><?= $p['rooms'] ?? '—' ?></div><div class="pd-stat-lbl">Pièces</div></div>
        <div class="pd-stat"><div class="pd-stat-val"><?= $p['bathrooms'] ?? '—' ?></div><div class="pd-stat-lbl">Sdb</div></div>
        <div class="pd-stat"><div class="pd-stat-val"><?= $p['views_count'] ?></div><div class="pd-stat-lbl">Vues</div></div>
      </div>

      <!-- Description -->
      <div class="pd-desc-block">
        <div class="pd-block-title">Description</div>
        <p class="pd-desc-text <?= $p['description'] ? '' : 'empty' ?>">
          <?= $p['description'] ? nl2br(htmlspecialchars($p['description'])) : 'Aucune description renseignée.' ?>
        </p>
      </div>

      <!-- Features -->
      <?php if (!empty($features)): ?>
      <div class="pd-features-block">
        <div class="pd-block-title">Équipements</div>
        <div class="pd-features-grid">
          <?php foreach ($features as $feat): ?>
            <span class="pd-feat-tag">
              <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <?= htmlspecialchars($feat) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- CTA contact pour les non-agents -->
      <?php if (!$can_edit): ?>
      <div class="pd-contact-cta">
        <p>Intéressé par ce bien ? Contactez directement l'agent responsable.</p>
        <?php if ($p['agent_email']): ?>
          <a href="mailto:<?= htmlspecialchars($p['agent_email']) ?>" class="btn">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Contacter l'agent
          </a>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
          <a href="login.php" class="btn btn-secondary">Se connecter pour contacter</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      

    </div><!-- /left -->

    <div class="pd-right">

      <!-- Meta (stats internes visibles seulement par les agents/admin) -->
      <div class="pd-meta-card">
        <div class="pd-meta-header">Informations</div>
        <div class="pd-meta-list">
          <div class="pd-meta-row">
            <div class="pd-meta-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div><div class="pd-meta-lbl">Ajouté le</div><div class="pd-meta-val"><?= date('d/m/Y', strtotime($p['created_at'])) ?></div></div>
          </div>
          <?php if ($p['postal_code']): ?>
          <div class="pd-meta-row">
            <div class="pd-meta-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
            <div><div class="pd-meta-lbl">Code postal</div><div class="pd-meta-val"><?= htmlspecialchars($p['postal_code']) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if ($can_edit): ?>
          <div class="pd-meta-row">
            <div class="pd-meta-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
            <div><div class="pd-meta-lbl">Modifié le</div><div class="pd-meta-val"><?= date('d/m/Y', strtotime($p['updated_at'])) ?></div></div>
          </div>
          <div class="pd-meta-row">
            <div class="pd-meta-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
            <div><div class="pd-meta-lbl">Vues</div><div class="pd-meta-val"><?= $p['views_count'] ?> consultation<?= $p['views_count'] > 1 ? 's' : '' ?></div></div>
          </div>
          <div class="pd-meta-row">
            <div class="pd-meta-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/></svg></div>
            <div><div class="pd-meta-lbl">Photos</div><div class="pd-meta-val"><?= count($images) ?> photo<?= count($images) > 1 ? 's' : '' ?></div></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Agent -->
      <div class="pd-agent-card">
        <div class="pd-agent-inner">
          <div class="pd-agent-ava">
            <?php if ($p['agent_photo']): ?>
              <img src="<?= htmlspecialchars($p['agent_photo']) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(substr($p['agent_name'] ?? '?', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div class="pd-agent-role">Agent responsable</div>
          <div class="pd-agent-name"><?= htmlspecialchars($p['agent_name'] ?? '') ?></div>
          <?php if ($p['agent_city']): ?><div class="pd-agent-city"><?= htmlspecialchars($p['agent_city']) ?></div><?php endif; ?>
        </div>
        <div class="pd-agent-contacts">
          <?php if ($p['agent_phone']): ?>
            <a href="tel:<?= htmlspecialchars($p['agent_phone']) ?>" class="pd-agent-contact-row">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.28 19a19.45 19.45 0 0 1-6-6 19.79 19.79 0 0 1-3.93-8.56A2 2 0 0 1 3.22 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16.92z"/></svg>
              <?= htmlspecialchars($p['agent_phone']) ?>
            </a>
          <?php endif; ?>
          <?php if ($p['agent_email']): ?>
            <a href="mailto:<?= htmlspecialchars($p['agent_email']) ?>" class="pd-agent-contact-row">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <?= htmlspecialchars($p['agent_email']) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Agency -->
      <?php if ($p['agency_name']): ?>
      <a href="agency_detail.php?id=<?= $p['agency_id'] ?>" class="pd-agency-chip">
        <div class="pd-agency-logo">
          <?php if ($p['agency_logo']): ?>
            <img src="<?= htmlspecialchars($p['agency_logo']) ?>" alt="">
          <?php else: ?>
            <?= strtoupper(substr($p['agency_name'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="pd-agency-label">Agence</div>
          <div class="pd-agency-name"><?= htmlspecialchars($p['agency_name']) ?></div>
        </div>
        <svg style="margin-left:auto;color:var(--muted)" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
      <?php endif; ?>

    </div><!-- /right -->
  </div><!-- /layout -->

</div>
</div>

<!-- Lightbox -->
<?php if (!empty($images)): ?>
<div class="lb-overlay" id="lightbox" onclick="closeLightboxOnBg(event)">
  <button class="lb-close" onclick="closeLightbox()">×</button>
  <img class="lb-img" id="lb-img" src="" alt="">
  <div class="lb-nav">
    <button onclick="lbPrev()">‹</button>
    <span class="lb-counter" id="lb-counter"></span>
    <button onclick="lbNext()">›</button>
  </div>
</div>
<script>
const lbImages = <?= json_encode(array_map(fn($i) => $i['image_path'], $images)) ?>;
let lbIdx = 0;
function openLightbox(i) { lbIdx = i; document.getElementById('lightbox').classList.add('open'); updateLb(); }
function closeLightbox()  { document.getElementById('lightbox').classList.remove('open'); }
function closeLightboxOnBg(e) { if (e.target === document.getElementById('lightbox')) closeLightbox(); }
function updateLb() {
  document.getElementById('lb-img').src = lbImages[lbIdx];
  document.getElementById('lb-counter').textContent = (lbIdx+1) + ' / ' + lbImages.length;
}
function lbNext() { lbIdx = (lbIdx + 1) % lbImages.length; updateLb(); }
function lbPrev() { lbIdx = (lbIdx - 1 + lbImages.length) % lbImages.length; updateLb(); }
document.addEventListener('keydown', e => {
  if (!document.getElementById('lightbox').classList.contains('open')) return;
  if (e.key === 'ArrowRight') lbNext();
  if (e.key === 'ArrowLeft')  lbPrev();
  if (e.key === 'Escape')     closeLightbox();
});
</script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>