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

// Vérifier que la propriété appartient à l'agence de l'agent
$stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = ? LIMIT 1");
$stmt->execute([$agent_id]);
$agency_row = $stmt->fetch();
$agency_id  = $agency_row['agency_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND agency_id = ? LIMIT 1");
$stmt->execute([$property_id, $agency_id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    header('Location: properties.php');
    exit;
}

$error = null;

// Confirmation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Récupérer et supprimer les fichiers images
        $img_stmt = $pdo->prepare("SELECT image_path FROM property_images WHERE property_id = ?");
        $img_stmt->execute([$property_id]);
        $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as $img) {
            $full = '../' . $img['image_path'];
            if (file_exists($full)) unlink($full);
        }
        // Supprimer la propriété (cascade supprime les images en BDD)
        $pdo->prepare("DELETE FROM properties WHERE id = ?")->execute([$property_id]);
        header('Location: properties.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Image principale pour aperçu
$main_img = $pdo->prepare("SELECT image_path FROM property_images WHERE property_id = ? AND is_main = 1 LIMIT 1");
$main_img->execute([$property_id]);
$main_image = $main_img->fetchColumn();

$type_labels = ['house'=>'Maison','apartment'=>'Appartement','land'=>'Terrain','commercial'=>'Local commercial'];

require '../includes/header.php';
?>

<style>
.del-wrap {
  min-height: calc(100vh - 64px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
}
.del-card {
  background: var(--surf);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  overflow: hidden;
  animation: fadeUp .4s ease both;
}
.del-top {
  padding: 32px 36px 24px;
  text-align: center;
  border-bottom: 1px solid var(--border);
}
.del-icon {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: rgba(239,68,68,.1);
  border: 1px solid rgba(239,68,68,.25);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--red);
  margin: 0 auto 16px;
}
.del-title {
  font-family: 'Syne', sans-serif;
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 6px;
}
.del-sub { font-size: 14px; color: var(--muted); }

.del-preview {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 24px;
  margin: 20px 36px;
  background: var(--surf2);
  border: 1px solid var(--border);
  border-radius: var(--r);
}
.del-preview-img {
  width: 60px; height: 60px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--bg);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: var(--border);
}
.del-preview-img img { width: 100%; height: 100%; object-fit: cover; }
.del-preview-name { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.del-preview-meta { font-size: 12px; color: var(--muted); }

.del-warning {
  margin: 0 36px 20px;
  background: rgba(239,68,68,.06);
  border: 1px solid rgba(239,68,68,.2);
  border-radius: var(--r);
  padding: 12px 16px;
  font-size: 13px;
  color: #FCA5A5;
}

.del-actions {
  padding: 20px 36px 32px;
  display: flex;
  gap: 10px;
}
.del-actions form { flex: 1; }
.del-actions form button { width: 100%; justify-content: center; padding: 13px; }
.del-actions a { flex: 1; justify-content: center; padding: 13px; }
</style>

<div class="del-wrap">
    <div class="del-card">
        <div class="del-top">
            <div class="del-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </div>
            <div class="del-title">Supprimer la propriété</div>
            <div class="del-sub">Cette action est irréversible.</div>
        </div>

        <?php if ($error): ?>
            <div class="error" style="margin:16px 36px 0;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Aperçu -->
        <div class="del-preview">
            <div class="del-preview-img">
                <?php if ($main_image): ?>
                    <img src="../<?= htmlspecialchars($main_image) ?>" alt="">
                <?php else: ?>
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <?php endif; ?>
            </div>
            <div>
                <div class="del-preview-name"><?= htmlspecialchars($property['title']) ?></div>
                <div class="del-preview-meta">
                    <?= $type_labels[$property['property_type']] ?? $property['property_type'] ?>
                    &nbsp;·&nbsp; <?= htmlspecialchars($property['city']) ?>
                    &nbsp;·&nbsp; <?= number_format($property['price'], 0, ',', ' ') ?> €
                </div>
            </div>
        </div>

        <div class="del-warning">
            ⚠️ Toutes les photos, données et informations liées à ce bien seront définitivement supprimées.
        </div>

        <div class="del-actions">
            <a href="property_detail.php?id=<?= $property_id ?>" class="btn btn-secondary">Annuler</a>
            <form method="POST">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger">Oui, supprimer</button>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>