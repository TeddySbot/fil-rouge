<?php
session_start();
require 'config/database.php';

use App\Repository\PropertyRepository;

$filter_type   = isset($_GET['type'])   && in_array($_GET['type'], ['house','apartment','land','commercial']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['available','sold','rented']) ? $_GET['status'] : 'available';
$filter_city   = trim($_GET['city'] ?? '');
$search        = trim($_GET['q'] ?? '');

// Couche POO : la page délègue l'accès aux données au repository (DRY + sécurité).
// $properties est un tableau d'objets App\Entity\Property.
$propertyRepo = new PropertyRepository($pdo);
$properties   = $propertyRepo->search([
    'status' => $filter_status,
    'type'   => $filter_type,
    'city'   => $filter_city,
    'q'      => $search,
]);

$status_colors = ['available'=>'badge-green','sold'=>'badge-gold','rented'=>'badge-blue'];

require 'includes/header.php';
?>

<div class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <div class="page-eyebrow">Catalogue</div>
            <h1 style="margin-bottom:0">Propriétés</h1>
        </div>
        <span style="font-size:13px;color:var(--muted)"><?= count($properties) ?> résultat<?= count($properties) > 1 ? 's' : '' ?></span>
    </div>

    <!-- Filtres -->
    <form method="GET">
        <div class="filter-bar">
            <svg width="14" height="14" fill="none" stroke="var(--muted)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="Titre, ville…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:160px;">
            <input type="text" name="city" placeholder="Ville" value="<?= htmlspecialchars($filter_city) ?>" style="width:140px;">
            <select name="type" style="width:auto;">
                <option value="">Tous les types</option>
                <option value="house"      <?= $filter_type==='house'      ?'selected':'' ?>>Maison</option>
                <option value="apartment"  <?= $filter_type==='apartment'  ?'selected':'' ?>>Appartement</option>
                <option value="land"       <?= $filter_type==='land'       ?'selected':'' ?>>Terrain</option>
                <option value="commercial" <?= $filter_type==='commercial' ?'selected':'' ?>>Local commercial</option>
            </select>
            <select name="status" style="width:auto;">
                <option value="">Tous les statuts</option>
                <option value="available" <?= $filter_status==='available'?'selected':'' ?>>Disponible</option>
                <option value="sold"      <?= $filter_status==='sold'     ?'selected':'' ?>>Vendu</option>
                <option value="rented"    <?= $filter_status==='rented'   ?'selected':'' ?>>Loué</option>
            </select>
            <button type="submit" class="btn btn-sm">Filtrer</button>
            <?php if ($search || $filter_type || $filter_city || $filter_status !== 'available'): ?>
                <a href="properties.php" class="btn btn-secondary btn-sm">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($properties)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;opacity:.3"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <p>Aucun bien ne correspond à vos critères.</p>
            <a href="properties.php" class="btn btn-secondary btn-sm">Réinitialiser les filtres</a>
        </div>
    <?php else: ?>
        <div class="properties-grid">
            <?php foreach ($properties as $i => $p):
                $sc = $status_colors[$p->status] ?? 'badge-gray';
            ?>
            <div class="property-card" style="animation-delay:<?= min($i*0.05,0.4) ?>s">
                <div class="property-card-img">
                    <?php if ($p->mainImage): ?>
                        <img src="<?= htmlspecialchars($p->mainImage) ?>" alt="<?= htmlspecialchars($p->title) ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--border);">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                    <?php endif; ?>
                    <div style="position:absolute;top:10px;left:10px;display:flex;gap:6px;">
                        <span class="badge <?= $sc ?>"><?= htmlspecialchars($p->statusLabel()) ?></span>
                        <?php if ($p->isFeatured): ?><span class="badge badge-gold">★</span><?php endif; ?>
                    </div>
                </div>
                <div class="property-card-body">
                    <div class="property-card-price"><?= $p->formattedPrice() ?></div>
                    <div class="property-card-title"><?= htmlspecialchars($p->title) ?></div>
                    <div class="property-card-city">
                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        <?= htmlspecialchars($p->city) ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($p->typeLabel()) ?>
                    </div>
                    <div class="property-card-meta">
                        <?php if ($p->surface): ?><span>⬛ <?= $p->surface ?> m²</span><?php endif; ?>
                        <?php if ($p->rooms): ?><span>🚪 <?= $p->rooms ?> p.</span><?php endif; ?>
                        <?php if ($p->bathrooms): ?><span>🚿 <?= $p->bathrooms ?></span><?php endif; ?>
                    </div>
                </div>
                <div style="padding:12px 20px;border-top:1px solid var(--border);">
                    <a href="property_detail.php?id=<?= $p->id ?>" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;">Voir le détail</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>