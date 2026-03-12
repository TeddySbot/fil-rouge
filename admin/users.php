<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = null;
$success = null;

if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $success = "Utilisateur supprimé";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

if (isset($_POST['update_role'])) {
    $user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['client', 'agent', 'admin'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute(['role' => $role, 'id' => $user_id]);
            $success = "Rôle mis à jour";
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

if (isset($_POST['toggle_status'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch();
        
        $new_status = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $user_id]);
        $success = "Statut utilisateur mis à jour";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query(
        "SELECT * FROM users ORDER BY created_at DESC"
    );
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
    $users = [];
}

require '../includes/header.php';
?>

<div class="container">
    <h1>Gestion des utilisateurs</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Ville</th>
                <th>Rôle</th>
                <th>Statut</th>
                <th>Date d'inscription</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($user['city'] ?? '-') ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role" onchange="this.form.submit()">
                                <option value="client" <?= $user['role'] === 'client' ? 'selected' : '' ?>>Client</option>
                                <option value="agent" <?= $user['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <input type="hidden" name="update_role" value="1">
                        </form>
                    </td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" name="toggle_status" value="1" class="btn btn-sm" style="background: <?= $user['is_active'] ? 'green' : 'red' ?>;">
                                <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                            </button>
                        </form>
                    </td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr?');">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary">Retour</a>
</div>

<?php require '../includes/footer.php'; ?>
