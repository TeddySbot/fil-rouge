<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id    = $_SESSION['user_id'];
$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$property_id) {
    header('Location: properties.php');
    exit;
}

// Agence de l'agent
$stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
$agency_row = $stmt->fetch(PDO::FETCH_ASSOC);
$agency_id  = $agency_row['agency_id'] ?? null;

// Récupérer la propriété (doit appartenir à l'agence)
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND agency_id = ? LIMIT 1");
$stmt->execute([$property_id, $agency_id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header('Location: properties.php');
    exit;
}

// Images existantes
$img_stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_main DESC, id ASC");
$img_stmt->execute([$property_id]);
$existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

$error   = null;
$success = null;

// ── Traitement du formulaire ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['_action'] ?? 'save';

    // Suppression d'une image
    if ($action === 'delete_image' && isset($_POST['image_id'])) {
        $img_id = (int)$_POST['image_id'];
        $del    = $pdo->prepare("SELECT image_path FROM property_images WHERE id = ? AND property_id = ?");
        $del->execute([$img_id, $property_id]);
        $img_row = $del->fetch();
        if ($img_row) {
            $full = '../' . $img_row['image_path'];
            if (file_exists($full)) unlink($full);
            $pdo->prepare("DELETE FROM property_images WHERE id = ?")->execute([$img_id]);
            // Si c'était la principale, promouvoir la suivante
            $pdo->prepare("UPDATE property_images SET is_main = 1 WHERE property_id = ? ORDER BY id ASC LIMIT 1")->execute([$property_id]);
            $success = "Image supprimée.";
        }
        // Recharger les images
        $img_stmt->execute([$property_id]);
        $existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Définir image principale
    } elseif ($action === 'set_main' && isset($_POST['image_id'])) {
        $img_id = (int)$_POST['image_id'];
        $pdo->prepare("UPDATE property_images SET is_main = 0 WHERE property_id = ?")->execute([$property_id]);
        $pdo->prepare("UPDATE property_images SET is_main = 1 WHERE id = ? AND property_id = ?")->execute([$img_id, $property_id]);
        $success = "Image principale mise à jour.";
        $img_stmt->execute([$property_id]);
        $existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sauvegarde principale
    } else {
        $title         = trim($_POST['title'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $price         = floatval($_POST['price'] ?? 0);
        $surface       = intval($_POST['surface'] ?? 0);
        $city          = trim($_POST['city'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $postal_code   = trim($_POST['postal_code'] ?? '');
        $property_type = $_POST['property_type'] ?? '';
        $rooms         = intval($_POST['rooms'] ?? 0) ?: null;
        $bathrooms     = intval($_POST['bathrooms'] ?? 0) ?: null;
        $status        = $_POST['status'] ?? 'available';
        $is_featured   = isset($_POST['is_featured']) ? 1 : 0;
        $features_raw  = array_filter(array_map('trim', explode(',', $_POST['features'] ?? '')));
        $features      = !empty($features_raw) ? json_encode(array_values($features_raw)) : null;

        $valid_types    = ['house','apartment','land','commercial'];
        $valid_statuses = ['available','sold','rented'];

        if (!$title)                                    $error = "Le titre est obligatoire.";
        elseif ($price <= 0)                            $error = "Le prix doit être supérieur à 0.";
        elseif ($surface <= 0)                          $error = "La surface doit être supérieure à 0.";
        elseif (!$city)                                 $error = "La ville est obligatoire.";
        elseif (!in_array($property_type, $valid_types))   $error = "Type de propriété invalide.";
        elseif (!in_array($status, $valid_statuses))        $error = "Statut invalide.";

        if (!$error) {
            try {
                $upd = $pdo->prepare("
                    UPDATE properties SET
                      title=?, description=?, price=?, surface=?, city=?, address=?,
                      postal_code=?, property_type=?, rooms=?, bathrooms=?,
                      status=?, features=?, is_featured=?
                    WHERE id=?
                ");
                $upd->execute([
                    $title, $description, $price, $surface, $city, $address,
                    $postal_code, $property_type, $rooms, $bathrooms,
                    $status, $features, $is_featured, $property_id
                ]);

                // Nouvelles images
                if (!empty($_FILES['new_images']['name'][0])) {
                    $upload_dir = '../uploads/properties/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $allowed = ['image/jpeg','image/png','image/webp'];
                    $has_main = !empty($existing_images);

                    foreach ($_FILES['new_images']['tmp_name'] as $k => $tmp) {
                        if ($_FILES['new_images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                        $mime = mime_content_type($tmp);
                        if (!in_array($mime, $allowed)) continue;
                        $ext      = pathinfo($_FILES['new_images']['name'][$k], PATHINFO_EXTENSION);
                        $filename = 'prop_' . $property_id . '_' . uniqid() . '.' . $ext;
                        $dest     = $upload_dir . $filename;
                        if (move_uploaded_file($tmp, $dest)) {
                            $ins = $pdo->prepare("INSERT INTO property_images (property_id, image_path, is_main) VALUES (?,?,?)");
                            $ins->execute([$property_id, 'uploads/properties/' . $filename, $has_main ? 0 : 1]);
                            $has_main = true;
                        }
                    }
                }

                // Recharger la prop
                $stmt->execute([$property_id, $agency_id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                $img_stmt->execute([$property_id]);
                $existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

                $success = "Propriété mise à jour avec succès.";

            } catch (Exception $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

// Features pour affichage
$features_display = '';
if ($p['features']) {
    $dec = json_decode($p['features'], true);
    if (is_array($dec)) $features_display = implode(', ', $dec);
}

$type_labels = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];

require '../includes/header.php';
?>


<div class="ed-page">
<div class="ed-wrap">

  <!-- Topbar -->
  <div class="ed-topbar">
    <a href="property_detail.php?id=<?= $property_id ?>" class="ed-back">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Retour au détail
    </a>
    <a href="property_detail.php?id=<?= $property_id ?>" class="ed-view-btn">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Voir la fiche
    </a>
  </div>

  <div class="ed-header">
    <div class="ed-eyebrow">Modifier</div>
    <h1 class="ed-title"><?= htmlspecialchars($p['title']) ?></h1>
  </div>

  <?php if ($error): ?>
    <div class="ed-alert error">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="ed-alert success">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <div class="ed-layout">
    <!-- LEFT -->
    <div class="ed-left">
      <form method="POST" enctype="multipart/form-data" id="main-form">
        <input type="hidden" name="_action" value="save">

        <!-- Infos générales -->
        <div class="ed-section">
          <div class="ed-section-head">
            <div class="ed-section-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg></div>
            <span class="ed-section-title">Informations générales</span>
          </div>
          <div class="ed-section-body" style="display:flex;flex-direction:column;gap:14px;">
            <div class="ed-field">
              <label class="ed-label">Titre <span class="req">*</span></label>
              <input type="text" name="title" class="ed-input" value="<?= htmlspecialchars($p['title']) ?>" required>
            </div>
            <div class="ed-g2">
              <div class="ed-field">
                <label class="ed-label">Type <span class="req">*</span></label>
                <select name="property_type" class="ed-select" required>
                  <option value="house"      <?= $p['property_type']==='house'      ?'selected':'' ?>>Maison</option>
                  <option value="apartment"  <?= $p['property_type']==='apartment'  ?'selected':'' ?>>Appartement</option>
                  <option value="land"       <?= $p['property_type']==='land'       ?'selected':'' ?>>Terrain</option>
                  <option value="commercial" <?= $p['property_type']==='commercial' ?'selected':'' ?>>Local commercial</option>
                </select>
              </div>
              <div class="ed-field">
                <label class="ed-label">Statut</label>
                <select name="status" class="ed-select">
                  <option value="available" <?= $p['status']==='available'?'selected':'' ?>>Disponible</option>
                  <option value="sold"      <?= $p['status']==='sold'     ?'selected':'' ?>>Vendu</option>
                  <option value="rented"    <?= $p['status']==='rented'   ?'selected':'' ?>>Loué</option>
                </select>
              </div>
            </div>
            <div class="ed-field">
              <label class="ed-label">Description</label>
              <textarea name="description" class="ed-textarea"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- Prix & surface -->
        <div class="ed-section">
          <div class="ed-section-head">
            <div class="ed-section-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <span class="ed-section-title">Prix & superficie</span>
          </div>
          <div class="ed-section-body">
            <div class="ed-g3">
              <div class="ed-field">
                <label class="ed-label">Prix (€) <span class="req">*</span></label>
                <input type="number" name="price" class="ed-input" value="<?= $p['price'] ?>" min="0" step="1000" required>
              </div>
              <div class="ed-field">
                <label class="ed-label">Surface (m²) <span class="req">*</span></label>
                <input type="number" name="surface" class="ed-input" value="<?= $p['surface'] ?>" min="1" required>
              </div>
              <div class="ed-field">
                <label class="ed-label">Pièces</label>
                <input type="number" name="rooms" class="ed-input" value="<?= $p['rooms'] ?? '' ?>" min="0">
              </div>
              <div class="ed-field">
                <label class="ed-label">Salles de bain</label>
                <input type="number" name="bathrooms" class="ed-input" value="<?= $p['bathrooms'] ?? '' ?>" min="0">
              </div>
            </div>
          </div>
        </div>

        <!-- Localisation -->
        <div class="ed-section">
          <div class="ed-section-head">
            <div class="ed-section-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
            <span class="ed-section-title">Localisation</span>
          </div>
          <div class="ed-section-body">
            <div class="ed-g2">
              <div class="ed-field ed-span2">
                <label class="ed-label">Adresse</label>
                <input type="text" name="address" class="ed-input" value="<?= htmlspecialchars($p['address'] ?? '') ?>">
              </div>
              <div class="ed-field">
                <label class="ed-label">Ville <span class="req">*</span></label>
                <input type="text" name="city" class="ed-input" value="<?= htmlspecialchars($p['city']) ?>" required>
              </div>
              <div class="ed-field">
                <label class="ed-label">Code postal</label>
                <input type="text" name="postal_code" class="ed-input" value="<?= htmlspecialchars($p['postal_code'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- Équipements -->
        <div class="ed-section">
          <div class="ed-section-head">
            <div class="ed-section-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            <span class="ed-section-title">Équipements & options</span>
          </div>
          <div class="ed-section-body" style="display:flex;flex-direction:column;gap:14px;">
            <div class="ed-field">
              <label class="ed-label">Équipements</label>
              <input type="text" name="features" class="ed-input" value="<?= htmlspecialchars($features_display) ?>" placeholder="Garage, Piscine, Jardin…">
              <span class="ed-hint">Séparés par des virgules</span>
            </div>
            <label class="ed-toggle-row">
              <input type="checkbox" name="is_featured" <?= $p['is_featured'] ? 'checked' : '' ?>>
              <span class="ed-track"></span>
              <div>
                <div class="ed-toggle-lbl">Mettre en avant</div>
                <div class="ed-toggle-desc">Priorité dans les résultats.</div>
              </div>
            </label>
          </div>
        </div>

        <!-- Photos -->
        <div class="ed-section">
          <div class="ed-section-head">
            <div class="ed-section-icon"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
            <span class="ed-section-title">Photos (<?= count($existing_images) ?>)</span>
          </div>
          <div class="ed-section-body">
            <!-- Images existantes -->
            <?php if (!empty($existing_images)): ?>
            <div class="ed-images-grid">
              <?php foreach ($existing_images as $img): ?>
                <div class="ed-img-item <?= $img['is_main'] ? 'is-main' : '' ?>">
                  <img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="">
                  <?php if ($img['is_main']): ?>
                    <span class="ed-img-badge">principale</span>
                  <?php endif; ?>
                  <div class="ed-img-overlay">
                    <?php if (!$img['is_main']): ?>
                      <!-- Définir principale -->
                      <form method="POST" style="display:contents">
                        <input type="hidden" name="_action" value="set_main">
                        <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                        <button type="submit" class="ed-img-action main" title="Définir comme principale">★</button>
                      </form>
                    <?php endif; ?>
                    <!-- Supprimer -->
                    <form method="POST" style="display:contents" onsubmit="return confirm('Supprimer cette image ?')">
                      <input type="hidden" name="_action" value="delete_image">
                      <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                      <button type="submit" class="ed-img-action del" title="Supprimer">
                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Ajouter de nouvelles images -->
            <div class="ed-upload-zone">
              <input type="file" name="new_images[]" id="ed-images-input" multiple accept="image/jpeg,image/png,image/webp">
              <svg style="margin:0 auto 8px;display:block;color:var(--muted)" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
              <p class="ed-upload-txt">Ajouter des photos</p>
              <p class="ed-upload-hint">JPEG, PNG, WEBP</p>
            </div>
            <div id="ed-preview"></div>
          </div>
        </div>

      </form><!-- /main-form -->
    </div><!-- /left -->

    <!-- RIGHT -->
    <div class="ed-right">

      <!-- Quick info -->
      <div class="ed-info-card">
        <div class="ed-info-card-head">Informations actuelles</div>
        <div class="ed-info-row">
          <span class="ed-info-key">Prix</span>
          <span class="ed-info-val"><?= number_format($p['price'], 0, ',', ' ') ?> €</span>
        </div>
        <div class="ed-info-row">
          <span class="ed-info-key">Surface</span>
          <span class="ed-info-val"><?= $p['surface'] ?> m²</span>
        </div>
        <div class="ed-info-row">
          <span class="ed-info-key">Type</span>
          <span class="ed-info-val"><?= $type_labels[$p['property_type']] ?? $p['property_type'] ?></span>
        </div>
        <div class="ed-info-row">
          <span class="ed-info-key">Vues</span>
          <span class="ed-info-val"><?= $p['views_count'] ?></span>
        </div>
        <div class="ed-info-row">
          <span class="ed-info-key">Créé le</span>
          <span class="ed-info-val"><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
        </div>
      </div>

      <!-- Submit -->
      <div class="ed-submit-section">
        <button type="submit" form="main-form" class="btn-save">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Enregistrer les modifications
        </button>
        <a href="property_detail.php?id=<?= $property_id ?>" class="btn-cancel-link">Annuler</a>
      </div>

    </div><!-- /right -->
  </div><!-- /layout -->

</div>
</div>

<script>
document.getElementById('ed-images-input').addEventListener('change', function () {
  const preview = document.getElementById('ed-preview');
  preview.innerHTML = '';
  Array.from(this.files).forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'ed-preview-thumb';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
});
</script>

<?php require '../includes/footer.php'; ?>