<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id = $_SESSION['user_id'];
$error    = null;
$success  = null;

// Récupérer l'agence de l'agent
$stmt = $pdo->prepare("SELECT aa.agency_id, a.name AS agency_name FROM agency_agents aa JOIN agencies a ON a.id = aa.agency_id WHERE aa.agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
$agency_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agency_row) {
    $error = "Vous n'êtes rattaché à aucune agence. Contactez un administrateur.";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {

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

    // Features (JSON)
    $features_raw = array_filter(array_map('trim', explode(',', $_POST['features'] ?? '')));
    $features     = !empty($features_raw) ? json_encode(array_values($features_raw)) : null;

    // Validation
    $valid_types   = ['house','apartment','land','commercial'];
    $valid_statuses = ['available','sold','rented'];

    if (!$title)                              $error = "Le titre est obligatoire.";
    elseif ($price <= 0)                      $error = "Le prix doit être supérieur à 0.";
    elseif ($surface <= 0)                    $error = "La surface doit être supérieure à 0.";
    elseif (!$city)                           $error = "La ville est obligatoire.";
    elseif (!in_array($property_type, $valid_types))   $error = "Type de propriété invalide.";
    elseif (!in_array($status, $valid_statuses))        $error = "Statut invalide.";

    if (!$error) {
        try {
            $ins = $pdo->prepare("
                INSERT INTO properties
                  (title, description, price, surface, city, address, postal_code,
                   property_type, rooms, bathrooms, agent_id, agency_id, status, features, is_featured)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $ins->execute([
                $title, $description, $price, $surface, $city, $address, $postal_code,
                $property_type, $rooms, $bathrooms, $agent_id, $agency_row['agency_id'],
                $status, $features, $is_featured
            ]);
            $property_id = $pdo->lastInsertId();

            // Upload des images
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = '../uploads/properties/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed = ['image/jpeg','image/png','image/webp'];
                $first   = true;

                foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                    $mime = mime_content_type($tmp);
                    if (!in_array($mime, $allowed)) continue;

                    $ext      = pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION);
                    $filename = 'prop_' . $property_id . '_' . uniqid() . '.' . $ext;
                    $dest     = $upload_dir . $filename;

                    if (move_uploaded_file($tmp, $dest)) {
                        $img_stmt = $pdo->prepare("INSERT INTO property_images (property_id, image_path, is_main) VALUES (?, ?, ?)");
                        $img_stmt->execute([$property_id, 'uploads/properties/' . $filename, $first ? 1 : 0]);
                        $first = false;
                    }
                }
            }

            header('Location: properties.php?created=1');
            exit;

        } catch (Exception $e) {
            $error = "Erreur lors de la création : " . $e->getMessage();
        }
    }
}

require '../includes/header.php';
?>



<div class="cr-page">
<div class="cr-wrap">

  <a href="properties.php" class="cr-back">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Retour aux propriétés
  </a>

  <div class="cr-header">
    <div class="cr-eyebrow"><?= htmlspecialchars($agency_row['agency_name'] ?? '') ?></div>
    <h1 class="cr-title">Créer une propriété</h1>
  </div>

  <?php if ($error): ?>
    <div class="cr-alert error">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$agency_row): ?>
  <?php else: ?>

  <form method="POST" enctype="multipart/form-data" class="cr-form">

    <!-- Infos générales -->
    <div class="cr-section">
      <div class="cr-section-head">
        <div class="cr-section-icon">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        </div>
        <span class="cr-section-title">Informations générales</span>
      </div>
      <div class="cr-section-body">
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="cr-field">
            <label class="cr-label">Titre <span class="req">*</span></label>
            <input type="text" name="title" class="cr-input" placeholder="Ex : Bel appartement lumineux T3" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
          </div>
          <div class="cr-grid-2">
            <div class="cr-field">
              <label class="cr-label">Type de bien <span class="req">*</span></label>
              <select name="property_type" class="cr-select" required>
                <option value="">— Sélectionner —</option>
                <option value="house"      <?= ($_POST['property_type'] ?? '') === 'house'      ? 'selected' : '' ?>>Maison</option>
                <option value="apartment"  <?= ($_POST['property_type'] ?? '') === 'apartment'  ? 'selected' : '' ?>>Appartement</option>
                <option value="land"       <?= ($_POST['property_type'] ?? '') === 'land'       ? 'selected' : '' ?>>Terrain</option>
                <option value="commercial" <?= ($_POST['property_type'] ?? '') === 'commercial' ? 'selected' : '' ?>>Local commercial</option>
              </select>
            </div>
            <div class="cr-field">
              <label class="cr-label">Statut</label>
              <select name="status" class="cr-select">
                <option value="available" <?= ($_POST['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Disponible</option>
                <option value="sold"      <?= ($_POST['status'] ?? '') === 'sold'      ? 'selected' : '' ?>>Vendu</option>
                <option value="rented"    <?= ($_POST['status'] ?? '') === 'rented'    ? 'selected' : '' ?>>Loué</option>
              </select>
            </div>
          </div>
          <div class="cr-field">
            <label class="cr-label">Description</label>
            <textarea name="description" class="cr-textarea" placeholder="Décrivez le bien, ses points forts, l'environnement…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Prix & surface -->
    <div class="cr-section">
      <div class="cr-section-head">
        <div class="cr-section-icon">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <span class="cr-section-title">Prix & superficie</span>
      </div>
      <div class="cr-section-body">
        <div class="cr-grid-3">
          <div class="cr-field">
            <label class="cr-label">Prix (€) <span class="req">*</span></label>
            <input type="number" name="price" class="cr-input" placeholder="250000" min="0" step="1000" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
          </div>
          <div class="cr-field">
            <label class="cr-label">Surface (m²) <span class="req">*</span></label>
            <input type="number" name="surface" class="cr-input" placeholder="75" min="1" value="<?= htmlspecialchars($_POST['surface'] ?? '') ?>" required>
          </div>
          <div class="cr-field">
            <label class="cr-label">Pièces</label>
            <input type="number" name="rooms" class="cr-input" placeholder="4" min="0" value="<?= htmlspecialchars($_POST['rooms'] ?? '') ?>">
          </div>
          <div class="cr-field">
            <label class="cr-label">Salles de bain</label>
            <input type="number" name="bathrooms" class="cr-input" placeholder="1" min="0" value="<?= htmlspecialchars($_POST['bathrooms'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Localisation -->
    <div class="cr-section">
      <div class="cr-section-head">
        <div class="cr-section-icon">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
        </div>
        <span class="cr-section-title">Localisation</span>
      </div>
      <div class="cr-section-body">
        <div class="cr-grid-2">
          <div class="cr-field cr-span-2">
            <label class="cr-label">Adresse</label>
            <input type="text" name="address" class="cr-input" placeholder="12 rue des Fleurs" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
          </div>
          <div class="cr-field">
            <label class="cr-label">Ville <span class="req">*</span></label>
            <input type="text" name="city" class="cr-input" placeholder="Paris" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
          </div>
          <div class="cr-field">
            <label class="cr-label">Code postal</label>
            <input type="text" name="postal_code" class="cr-input" placeholder="75001" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Équipements & options -->
    <div class="cr-section">
      <div class="cr-section-head">
        <div class="cr-section-icon">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <span class="cr-section-title">Équipements & options</span>
      </div>
      <div class="cr-section-body" style="display:flex;flex-direction:column;gap:16px;">
        <div class="cr-field">
          <label class="cr-label">Équipements / Caractéristiques</label>
          <input type="text" name="features" class="cr-input" placeholder="Garage, Piscine, Balcon, Cave, Parking…" value="<?= htmlspecialchars($_POST['features'] ?? '') ?>">
          <span class="cr-hint">Séparez par des virgules. Ex : Garage, Jardin, Piscine</span>
        </div>
        <label class="cr-toggle-row">
          <input type="checkbox" name="is_featured" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>>
          <span class="cr-toggle-track"></span>
          <div>
            <div class="cr-toggle-label">Mettre en avant ce bien</div>
            <div class="cr-toggle-desc">Le bien apparaîtra en priorité dans les résultats.</div>
          </div>
        </label>
      </div>
    </div>

    <!-- Photos -->
    <div class="cr-section">
      <div class="cr-section-head">
        <div class="cr-section-icon">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <span class="cr-section-title">Photos</span>
      </div>
      <div class="cr-section-body">
        <div class="cr-upload-zone" id="upload-zone">
          <input type="file" name="images[]" id="images-input" multiple accept="image/jpeg,image/png,image/webp">
          <svg class="cr-upload-icon" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
          <p class="cr-upload-text">Glissez vos photos ici ou cliquez pour parcourir</p>
          <p class="cr-upload-hint">JPEG, PNG, WEBP — La première image sera l'image principale</p>
        </div>
        <div id="image-preview"></div>
      </div>
    </div>

    <!-- Actions -->
    <div class="cr-actions">
      <a href="properties.php" class="btn-cancel">Annuler</a>
      <button type="submit" class="btn-submit">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Créer la propriété
      </button>
    </div>

  </form>
  <?php endif; ?>

</div>
</div>

<script>
document.getElementById('images-input').addEventListener('change', function () {
  const preview = document.getElementById('image-preview');
  preview.innerHTML = '';
  Array.from(this.files).forEach((file, i) => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'img-thumb';
      img.title = i === 0 ? 'Image principale' : file.name;
      if (i === 0) img.style.borderColor = 'var(--gold)';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
});
</script>

<?php require '../includes/footer.php'; ?>