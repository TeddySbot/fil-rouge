<?php
session_start();
require 'config/database.php';

// IDs depuis l'URL (max 3)
$raw_ids = explode(',', $_GET['ids'] ?? '');
$ids = array_slice(array_filter(array_map('intval', $raw_ids)), 0, 3);

$properties = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*,
               u.name AS agent_name, u.phone AS agent_phone, u.email AS agent_email,
               a.name AS agency_name,
               (SELECT image_path FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS main_img
        FROM properties p
        LEFT JOIN users u ON u.id = p.agent_id
        LEFT JOIN agencies a ON a.id = p.agency_id
        WHERE p.id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Biens disponibles pour sélection
$all_props = $pdo->query("SELECT id, title, city, property_type, price FROM properties WHERE status = 'available' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$type_labels   = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];
$status_labels = ['available'=>'Disponible','sold'=>'Vendu','rented'=>'Loué'];
$status_colors = ['available'=>'badge-green','sold'=>'badge-gold','rented'=>'badge-blue'];

require 'includes/header.php';
?>

<div class="container">
  <div class="page-eyebrow">Outil client</div>
  <h1>Comparateur de biens</h1>

  <!-- Sélecteur -->
  <div class="form-section" style="margin-bottom:32px;">
    <div class="block-title">Sélectionner les biens à comparer (max 3)</div>
    <form method="GET" action="">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px;">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="form-group" style="margin:0">
            <label>Bien <?= $i+1 ?></label>
            <select name="ids[]">
              <option value="">— Aucun —</option>
              <?php foreach ($all_props as $ap): ?>
                <option value="<?= $ap['id'] ?>" <?= in_array($ap['id'], $ids) && ($ids[$i] ?? 0) == $ap['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(mb_strimwidth($ap['title'],0,40,'…')) ?> — <?= number_format($ap['price'],0,',',' ') ?> €
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endfor; ?>
      </div>
      <button type="submit" class="btn btn-sm">Comparer</button>
    </form>
  </div>

  <?php if (empty($properties)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--muted);">
      <div style="font-size:40px;margin-bottom:12px;">⚖️</div>
      <p>Sélectionnez au moins 2 biens pour les comparer.</p>
    </div>
  <?php else: ?>

  <!-- Tableau comparatif -->
  <div class="cmp-table-wrap">
    <table class="cmp-table">
      <!-- Photos -->
      <tr class="cmp-row-photos">
        <td class="cmp-label-col"></td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col">
            <div class="cmp-photo">
              <?php if ($p['main_img']): ?>
                <img src="<?= htmlspecialchars($p['main_img']) ?>" alt="">
              <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--border);">
                  <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
              <?php endif; ?>
            </div>
            <div class="cmp-prop-title"><?= htmlspecialchars($p['title']) ?></div>
            <span class="badge <?= $status_colors[$p['status']] ?? 'badge-gray' ?>"><?= $status_labels[$p['status']] ?? $p['status'] ?></span>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Prix -->
      <?php
      $prices = array_column($properties, 'price');
      $min_price = min($prices);
      ?>
      <tr class="cmp-row">
        <td class="cmp-label-col">Prix</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col <?= $p['price'] == $min_price ? 'cmp-best' : '' ?>">
            <span class="cmp-price"><?= number_format($p['price'],0,',',' ') ?> €</span>
            <?php if ($p['price'] == $min_price): ?><div class="cmp-badge-best">Moins cher</div><?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Prix/m² -->
      <?php $pm2s = []; foreach ($properties as $p) { $pm2s[$p['id']] = $p['surface'] > 0 ? round($p['price']/$p['surface']) : 0; } $min_pm2 = min(array_filter($pm2s)); ?>
      <tr class="cmp-row">
        <td class="cmp-label-col">Prix / m²</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col <?= ($pm2s[$p['id']] == $min_pm2 && $min_pm2 > 0) ? 'cmp-best' : '' ?>">
            <?= $pm2s[$p['id']] > 0 ? number_format($pm2s[$p['id']],0,',',' ').' €/m²' : '—' ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Surface -->
      <?php $surfaces = array_column($properties,'surface'); $max_surf = max($surfaces); ?>
      <tr class="cmp-row">
        <td class="cmp-label-col">Surface</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col <?= $p['surface'] == $max_surf ? 'cmp-best' : '' ?>">
            <?= $p['surface'] ?> m²
            <?php if ($p['surface'] == $max_surf): ?><div class="cmp-badge-best">Plus grand</div><?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Type -->
      <tr class="cmp-row">
        <td class="cmp-label-col">Type</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col"><?= $type_labels[$p['property_type']] ?? $p['property_type'] ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Pièces -->
      <?php $rooms = array_filter(array_column($properties,'rooms')); $max_rooms = $rooms ? max($rooms) : 0; ?>
      <tr class="cmp-row">
        <td class="cmp-label-col">Pièces</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col <?= ($p['rooms'] && $p['rooms'] == $max_rooms) ? 'cmp-best' : '' ?>">
            <?= $p['rooms'] ?? '—' ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Sdb -->
      <tr class="cmp-row">
        <td class="cmp-label-col">Salles de bain</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col"><?= $p['bathrooms'] ?? '—' ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Ville -->
      <tr class="cmp-row">
        <td class="cmp-label-col">Ville</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col">📍 <?= htmlspecialchars($p['city']) ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Équipements -->
      <tr class="cmp-row">
        <td class="cmp-label-col">Équipements</td>
        <?php foreach ($properties as $p):
          $feats = $p['features'] ? json_decode($p['features'], true) : [];
        ?>
          <td class="cmp-col">
            <?php if ($feats): ?>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <?php foreach ($feats as $f): ?>
                  <span class="badge badge-gray" style="font-size:9px;"><?= htmlspecialchars($f) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--muted)">—</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <!-- Agent -->
      <tr class="cmp-row">
        <td class="cmp-label-col">Agent</td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col" style="font-size:12px;color:var(--soft)"><?= htmlspecialchars($p['agent_name'] ?? '—') ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Actions -->
      <tr class="cmp-row-actions">
        <td class="cmp-label-col"></td>
        <?php foreach ($properties as $p): ?>
          <td class="cmp-col">
            <div style="display:flex;flex-direction:column;gap:8px;">
              <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm" style="justify-content:center;">Voir le bien</a>
              <a href="request_appointment.php?property=<?= $p['id'] ?>&agent=<?= $p['agent_id'] ?>" class="btn btn-secondary btn-sm" style="justify-content:center;">📅 Demander RDV</a>
              <form method="POST" action="favorites.php">
                <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="redirect" value="compare.php?ids=<?= implode(',', $ids) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;">♥ Favori</button>
              </form>
            </div>
          </td>
        <?php endforeach; ?>
      </tr>
    </table>
  </div>
  <?php endif; ?>

  <div style="margin-top:28px;display:flex;gap:10px;">
    <a href="properties.php" class="btn btn-secondary">← Retour aux biens</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="favorites.php" class="btn btn-secondary">♥ Mes favoris</a>
    <?php endif; ?>
  </div>
</div>

<?php require 'includes/footer.php'; ?>