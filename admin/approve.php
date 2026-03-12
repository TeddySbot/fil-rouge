<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = null;
$success = null;

if (isset($_POST['approve_id'])) {
    $user_id = (int)$_POST['approve_id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = 'agent' WHERE id = :id AND role = 'attente'");
        $stmt->execute(['id' => $user_id]);
        $success = "Agent approuvé avec succès";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

if (isset($_POST['refuse_id'])) {
    $user_id = (int)$_POST['refuse_id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = 'client' WHERE id = :id AND role = 'attente'");
        $stmt->execute(['id' => $user_id]);
        $success = "Candidature refusée - utilisateur reclassé en client";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query(
        "SELECT * FROM users WHERE role = 'attente' ORDER BY created_at DESC"
    );
    $pending_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
    $pending_users = [];
}

require '../includes/header.php';
?>

<div class="container">
    <h1>Approbation des Agents</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($pending_users)): ?>
        <p><strong>Aucun candidat en attente d'approbation.</strong></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Adresse</th>
                    <th>Ville</th>
                    <th>Date de candidature</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($user['address'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($user['city'] ?? '-') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="approve_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-success" style="margin-right: 5px;">Valider</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="refuse_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de refuser cette candidature?');">Refuser</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">Retour</a>
</div>

<?php require '../includes/footer.php'; ?>
