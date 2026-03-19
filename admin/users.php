<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = $success = null;

if (isset($_POST['delete_id'])) {
    try {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int)$_POST['delete_id']]);
        $success = "Utilisateur supprimé.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}
if (isset($_POST['update_role'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['client','agent','admin','attente'])) {
        try {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
            $success = "Rôle mis à jour.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}
if (isset($_POST['toggle_status'])) {
    $uid = (int)$_POST['user_id'];
    try {
        $cur = $pdo->prepare("SELECT is_active FROM users WHERE id=?");
        $cur->execute([$uid]);
        $row = $cur->fetch();
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$row['is_active'] ? 0 : 1, $uid]);
        $success = "Statut mis à jour.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$search     = trim($_GET['q'] ?? '');
$role_filter= $_GET['role'] ?? '';
$sql        = "SELECT * FROM users WHERE 1=1";
$params     = [];
if ($search)      { $sql .= " AND (name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($role_filter) { $sql .= " AND role = ?"; $params[] = $role_filter; }
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$role_labels = ['client'=>'Client','agent'=>'Agent','admin'=>'Admin','attente'=>'En attente'];
$role_badge  = ['client'=>'badge-gray','agent'=>'badge-green','admin'=>'badge-gold','attente'=>'badge-blue'];

require '../includes/header.php';
?>

<div class="container">
    <div class="dash-header">
        <div>
            <div class="page-eyebrow">Administration</div>
            <h1 style="margin-bottom:0">Utilisateurs</h1>
        </div>
        <a href="index.php" class="btn btn-secondary btn-sm">← Retour</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Filtres -->
    <form method="GET">
        <div class="filter-bar" style="margin-bottom:20px;">
            <input type="text" name="q" placeholder="Nom ou email…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
            <select name="role" style="width:auto;">
                <option value="">Tous les rôles</option>
                <?php foreach ($role_labels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $role_filter===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm">Filtrer</button>
            <?php if ($search || $role_filter): ?><a href="users.php" class="btn btn-secondary btn-sm">✕</a><?php endif; ?>
        </div>
    </form>

    <p style="font-size:13px;color:var(--muted);margin-bottom:16px;"><strong style="color:var(--text)"><?= count($users) ?></strong> utilisateur<?= count($users)>1?'s':'' ?></p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Ville</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--surf2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--gold);flex-shrink:0;">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                            <span style="color:var(--text);font-weight:500;"><?= htmlspecialchars($u['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($u['city'] ?? '—') ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="update_role" value="1">
                            <select name="role" onchange="this.form.submit()" style="width:auto;padding:5px 8px;font-size:12px;">
                                <?php foreach ($role_labels as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $u['role']===$k?'selected':'' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="toggle_status" value="1" class="btn btn-sm <?= $u['is_active'] ? 'btn-success' : 'btn-danger' ?>" style="min-width:70px;justify-content:center;">
                                <?= $u['is_active'] ? '● Actif' : '● Inactif' ?>
                            </button>
                        </form>
                    </td>
                    <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer <?= htmlspecialchars($u['name']) ?> ?')">Supprimer</button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:12px;color:var(--muted)">Vous</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>