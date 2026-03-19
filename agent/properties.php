<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id = $_SESSION['user_id'];

// Récupérer l'agence de l'agent
$stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
$agency_row = $stmt->fetch(PDO::FETCH_ASSOC);
$agency_id = $agency_row ? $agency_row['agency_id'] : null;

// Filtres
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['available','sold','rented']) ? $_GET['status'] : '';
$filter_type   = isset($_GET['type'])   && in_array($_GET['type'], ['house','apartment','land','commercial']) ? $_GET['type'] : '';
$search        = isset($_GET['q']) ? trim($_GET['q']) : '';

// Construction de la requête : tous les biens de l'agence
$where = ["p.agency_id = ?"];
$params = [$agency_id];

if ($filter_status) { $where[] = "p.status = ?";        $params[] = $filter_status; }
if ($filter_type)   { $where[] = "p.property_type = ?"; $params[] = $filter_type; }
if ($search)        { $where[] = "(p.title LIKE ? OR p.city LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT p.*,
           u.name AS agent_name,
           u.profile_image AS agent_photo,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS main_image
    FROM properties p
    LEFT JOIN users u ON u.id = p.agent_id
    WHERE $where_sql
    ORDER BY p.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats rapides
$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'available') AS available,
        SUM(status = 'sold')      AS sold,
        SUM(status = 'rented')    AS rented
    FROM properties WHERE agency_id = ?
");
$stats_stmt->execute([$agency_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Nom agence
$ag_stmt = $pdo->prepare("SELECT name FROM agencies WHERE id = ?");
$ag_stmt->execute([$agency_id]);
$agency = $ag_stmt->fetch(PDO::FETCH_ASSOC);

$type_labels  = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];
$status_labels = ['available'=>'Disponible','sold'=>'Vendu','rented'=>'Loué'];
$status_colors = ['available'=>'green','sold'=>'gold','rented'=>'blue'];

require '../includes/header.php';
?>


<div class="pr-page">
<div class="pr-wrap">

  <!-- Header -->
  <div class="pr-header">
    <div class="pr-title-block">
      <div class="pr-eyebrow">
        <?= $agency ? htmlspecialchars($agency['name']) : 'Mon agence' ?>
      </div>
      <h1 class="pr-title">Mes <span>Propriétés</span></h1>
    </div>
    <a href="property_create.php" class="btn-new">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Ajouter un bien
    </a>
  </div>

  <!-- Stats -->
  <div class="pr-stats">
    <div class="pr-stat">
      <div class="pr-stat-val soft"><?= $stats['total'] ?? 0 ?></div>
      <div class="pr-stat-label">Total</div>
    </div>
    <div class="pr-stat">
      <div class="pr-stat-val green"><?= $stats['available'] ?? 0 ?></div>
      <div class="pr-stat-label">Disponibles</div>
    </div>
    <div class="pr-stat">
      <div class="pr-stat-val gold"><?= $stats['sold'] ?? 0 ?></div>
      <div class="pr-stat-label">Vendus</div>
    </div>
    <div class="pr-stat">
      <div class="pr-stat-val blue"><?= $stats['rented'] ?? 0 ?></div>
      <div class="pr-stat-label">Loués</div>
    </div>
  </div>

  <!-- Filtres -->
  <form method="GET" action="">
    <div class="pr-filters">
      <div class="pr-search">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" placeholder="Rechercher un bien, une ville…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="status" class="pr-select">
        <option value="">Tous les statuts</option>
        <option value="available" <?= $filter_status === 'available' ? 'selected' : '' ?>>Disponible</option>
        <option value="sold"      <?= $filter_status === 'sold'      ? 'selected' : '' ?>>Vendu</option>
        <option value="rented"    <?= $filter_status === 'rented'    ? 'selected' : '' ?>>Loué</option>
      </select>
      <select name="type" class="pr-select">
        <option value="">Tous les types</option>
        <option value="house"      <?= $filter_type === 'house'      ? 'selected' : '' ?>>Maison</option>
        <option value="apartment"  <?= $filter_type === 'apartment'  ? 'selected' : '' ?>>Appartement</option>
        <option value="land"       <?= $filter_type === 'land'       ? 'selected' : '' ?>>Terrain</option>
        <option value="commercial" <?= $filter_type === 'commercial' ? 'selected' : '' ?>>Local commercial</option>
      </select>
      <button type="submit" class="btn-filter">Filtrer</button>
      <?php if ($filter_status || $filter_type || $search): ?>
        <a href="properties.php" class="btn-reset">✕ Réinitialiser</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Count -->
  <p class="pr-count"><strong><?= count($properties) ?></strong> bien<?= count($properties) > 1 ? 's' : '' ?> trouvé<?= count($properties) > 1 ? 's' : '' ?></p>

  <!-- Grille -->
  <div class="pr-grid">
    <?php if (empty($properties)): ?>
      <div class="pr-empty">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <p>Aucune propriété trouvée.</p>
        <a href="property_create.php" class="btn-new">Ajouter un premier bien</a>
      </div>
    <?php else: ?>
      <?php foreach ($properties as $i => $p):
        $sc = $status_colors[$p['status']] ?? 'soft';
        $sl = $status_labels[$p['status']] ?? $p['status'];
        $tl = $type_labels[$p['property_type']] ?? $p['property_type'];
        $delay = min($i * 0.05, 0.4);
      ?>
        <div class="pr-card" style="animation-delay: <?= $delay ?>s">
          <!-- Image -->
          <div class="pr-card-img">
            <?php if ($p['main_image']): ?>
              <img src="../<?= htmlspecialchars($p['main_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
            <?php else: ?>
              <div class="pr-card-img-placeholder">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </div>
            <?php endif; ?>
            <span class="pr-card-status <?= $sc ?>"><?= $sl ?></span>
            <?php if ($p['is_featured']): ?>
              <span class="pr-card-featured">★ Mis en avant</span>
            <?php endif; ?>
          </div>

          <!-- Body -->
          <div class="pr-card-body">
            <div class="pr-card-type"><?= $tl ?></div>
            <div class="pr-card-title" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></div>
            <div class="pr-card-city">
              <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
              <?= htmlspecialchars($p['city']) ?>
            </div>
            <div class="pr-card-price"><?= number_format($p['price'], 0, ',', ' ') ?> €</div>
            <div class="pr-card-meta">
              <?php if ($p['rooms']): ?>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                  <?= $p['rooms'] ?> pièce<?= $p['rooms'] > 1 ? 's' : '' ?>
                </span>
              <?php endif; ?>
              <?php if ($p['bathrooms']): ?>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12h16M4 12V6a2 2 0 0 1 2-2h3m0 0V2m0 2h6m0-2v2m0 0a2 2 0 0 1 2 2v6M4 12v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>
                  <?= $p['bathrooms'] ?> sdb
                </span>
              <?php endif; ?>
              <span>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
                <?= $p['surface'] ?> m²
              </span>
              <span>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?= $p['views_count'] ?>
              </span>
            </div>
          </div>

          <!-- Agent + actions -->
          <div class="pr-card-agent">
            <div class="pr-agent-avatar">
              <?php if ($p['agent_photo']): ?>
                <img src="../<?= htmlspecialchars($p['agent_photo']) ?>" alt="">
              <?php else: ?>
                <?= strtoupper(substr($p['agent_name'] ?? '?', 0, 1)) ?>
              <?php endif; ?>
            </div>
            <span class="pr-agent-name"><?= htmlspecialchars($p['agent_name'] ?? '') ?></span>
            <div class="pr-card-actions">
              <a href="property_edit.php?id=<?= $p['id'] ?>" class="pr-action-btn" title="Modifier">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <a href="../property_detail.php?id=<?= $p['id'] ?>" class="pr-action-btn" title="Voir" target="_blank">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <a href="property_delete.php?id=<?= $p['id'] ?>" class="pr-action-btn del" title="Supprimer" onclick="return confirm('Supprimer cette propriété ?')">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</div>

<?php require '../includes/footer.php'; ?>