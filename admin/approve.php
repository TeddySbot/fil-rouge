<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = $success = null;

if (isset($_POST['approve_id'])) {
    try {
        $pdo->prepare("UPDATE users SET role='agent' WHERE id=? AND role='attente'")->execute([(int)$_POST['approve_id']]);
        $success = "Agent approuvé avec succès.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}
if (isset($_POST['refuse_id'])) {
    try {
        $pdo->prepare("UPDATE users SET role='client' WHERE id=? AND role='attente'")->execute([(int)$_POST['refuse_id']]);
        $success = "Candidature refusée — utilisateur reclassé en client.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$pending = $pdo->query("SELECT * FROM users WHERE role = 'attente' ORDER BY created_at DESC")->fetchAll();

require '../includes/header.php';
?>

<div class="container">
    <div class="dash-header">
        <div>
            <div class="page-eyebrow">Administration</div>
            <h1 style="margin-bottom:0">Approbation des agents</h1>
        </div>
        <a href="index.php" class="btn btn-secondary btn-sm">← Retour</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($pending)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <div style="font-size:40px;margin-bottom:12px;">✅</div>
            <p><strong style="color:var(--text)">Aucune candidature en attente.</strong></p>
            <p>Toutes les demandes ont été traitées.</p>
        </div>
    <?php else: ?>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">
            <strong style="color:var(--gold)"><?= count($pending) ?></strong> candidature<?= count($pending)>1?'s':'' ?> en attente de validation.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Candidat</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Ville</th>
                        <th>Candidature</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:50%;background:rgba(212,168,67,.1);border:1px solid rgba(212,168,67,.2);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--gold);flex-shrink:0;">
                                    <?= strtoupper(substr($u['name'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="color:var(--text);font-weight:500;font-size:14px;"><?= htmlspecialchars($u['name']) ?></div>
                                    <?php if ($u['address']): ?><div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($u['address']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><a href="mailto:<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></a></td>
                        <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($u['city'] ?? '—') ?></td>
                        <td style="white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;gap:8px;">
                                <form method="post">
                                    <input type="hidden" name="approve_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">✓ Valider</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="refuse_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Refuser la candidature de <?= htmlspecialchars($u['name']) ?> ?')">✕ Refuser</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>