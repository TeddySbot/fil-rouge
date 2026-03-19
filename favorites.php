<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];

// Toggle favori (appelé en POST depuis n'importe quelle page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['property_id'])) {
    $pid = (int)$_POST['property_id'];
    $exists = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND property_id = ?");
    $exists->execute([$user_id, $pid]);
    if ($exists->fetch()) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?")->execute([$user_id, $pid]);
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, property_id) VALUES (?,?)")->execute([$user_id, $pid]);
    }
    // Redirect back
    $ref = $_POST['redirect'] ?? 'favorites.php';
    header("Location: $ref"); exit;
}

// Supprimer un favori
if (isset($_GET['remove'])) {
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?")->execute([$user_id, (int)$_GET['remove']]);
    header('Location: favorites.php'); exit;
}

// Récupérer les favoris
$favs = $pdo->prepare("
    SELECT p.*, f.created_at AS fav_at,
           u.name AS agent_name,
           a.name AS agency_name,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS main_img
    FROM favorites f
    JOIN properties p ON p.id = f.property_id
    LEFT JOIN users u ON u.id = p.agent_id
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$favs->execute([$user_id]); $favs = $favs->fetchAll(PDO::FETCH_ASSOC);

$type_labels   = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];
$status_colors = ['available'=>'badge-green','sold'=>'badge-gold','rented'=>'badge-blue'];
$status_labels = ['available'=>'Disponible','sold'=>'Vendu','rented'=>'Loué'];

// IDs pour comparateur
$fav_ids = array_column($favs, 'id');

require 'includes/header.php';
?>

<div class="container">
  <div class="dash-header">
    <div>
      <div class="page-eyebrow">Mon espace</div>
      <h1 style="margin-bottom:0">Mes favoris</h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <span style="font-size:13px;color:var(--muted)"><?= count($favs) ?> bien<?= count($favs)>1?'s':'' ?></span>
      <?php if (count($favs) >= 2): ?>
        <a href="compare.php?ids=<?= implode(',', array_slice($fav_ids, 0, 3)) ?>" class="btn btn-secondary btn-sm">
          ⚖️ Comparer les 3 premiers
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($favs)): ?>
    <div style="text-align:center;padding:70px 20px;color:var(--muted);">
      <div style="font-size:40px;margin-bottom:14px;">🤍</div>
      <p style="font-size:15px;margin-bottom:20px;">Vous n'avez pas encore de biens en favoris.</p>
      <a href="properties.php" class="btn">Parcourir les biens</a>
    </div>
  <?php else: ?>
    <div class="properties-grid">
      <?php foreach ($favs as $i => $p):
        $sc = $status_colors[$p['status']] ?? 'badge-gray';
      ?>
      <div class="property-card" style="animation-delay:<?= min($i*.05,.4) ?>s">
        <div class="property-card-img">
          <?php if ($p['main_img']): ?>
            <img src="<?= htmlspecialchars($p['main_img']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--border);">
              <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
          <?php endif; ?>
          <div style="position:absolute;top:10px;left:10px;">
            <span class="badge <?= $sc ?>"><?= $status_labels[$p['status']] ?? $p['status'] ?></span>
          </div>
          <!-- Bouton retirer favori -->
          <a href="favorites.php?remove=<?= $p['id'] ?>" onclick="return confirm('Retirer des favoris ?')"
             style="position:absolute;top:10px;right:10px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;text-decoration:none;" title="Retirer des favoris">
            ♥
          </a>
        </div>
        <div class="property-card-body">
          <div class="property-card-price"><?= number_format($p['price'],0,',',' ') ?> €</div>
          <div class="property-card-title"><?= htmlspecialchars($p['title']) ?></div>
          <div class="property-card-city">
            📍 <?= htmlspecialchars($p['city']) ?> · <?= $type_labels[$p['property_type']] ?? $p['property_type'] ?>
          </div>
          <div class="property-card-meta">
            <?php if ($p['surface']): ?><span>⬛ <?= $p['surface'] ?> m²</span><?php endif; ?>
            <?php if ($p['rooms']): ?><span>🚪 <?= $p['rooms'] ?> p.</span><?php endif; ?>
          </div>
          <?php if ($p['agent_name']): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:8px;">Agent : <?= htmlspecialchars($p['agent_name']) ?></div>
          <?php endif; ?>
        </div>
        <div style="padding:10px 16px;border-top:1px solid var(--border);display:flex;gap:8px;">
          <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">Voir le bien</a>
          <a href="request_appointment.php?property=<?= $p['id'] ?>&agent=<?= $p['agent_id'] ?>" class="btn btn-sm" style="flex:1;justify-content:center;">📅 RDV</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <a href="account.php" class="btn btn-secondary">← Mon compte</a>
</div>

<?php require 'includes/footer.php'; ?>