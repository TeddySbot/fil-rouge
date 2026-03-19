<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$error = $success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: index.php'); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        if ($_POST['action'] === 'update_profile') {
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city    = trim($_POST['city'] ?? '');

            if (!$name)                                             $error = "Le nom est requis.";
            elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Email invalide.";
            elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) $error = "Téléphone invalide.";
            else {
                $pdo->prepare("UPDATE users SET name=:name, email=:email, phone=:phone, address=:address, city=:city WHERE id=:id")
                    ->execute(['name'=>$name,'email'=>$email,'phone'=>$phone?:null,'address'=>$address?:null,'city'=>$city?:null,'id'=>$_SESSION['user_id']]);
                $_SESSION['name'] = $name;
                $success = "Profil mis à jour avec succès.";
                $stmt->execute(['id' => $_SESSION['user_id']]);
                $user = $stmt->fetch();
            }

        } elseif ($_POST['action'] === 'update_password') {
            $old = $_POST['old_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $cfm = $_POST['confirm_password'] ?? '';
            if (!password_verify($old, $user['password']))  $error = "Ancien mot de passe incorrect.";
            elseif (strlen($new) < 6)                       $error = "Le nouveau mot de passe doit faire au moins 6 caractères.";
            elseif ($new !== $cfm)                          $error = "Les mots de passe ne correspondent pas.";
            else {
                $pdo->prepare("UPDATE users SET password=:pw WHERE id=:id")
                    ->execute(['pw' => password_hash($new, PASSWORD_DEFAULT), 'id' => $_SESSION['user_id']]);
                $success = "Mot de passe mis à jour.";
            }
        }
    }
} catch (PDOException $e) { $error = "Erreur : " . $e->getMessage(); }

$role_labels = ['client'=>'Client','agent'=>'Agent','admin'=>'Administrateur','attente'=>'En attente'];
$active_tab  = isset($_GET['tab']) && $_GET['tab'] === 'password' ? 'password' : 'profile';

require 'includes/header.php';
?>

<div class="container">
    <h1>Mon Compte</h1>

    <?php if ($error): ?>
        <div class="error"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="account-layout">

        <!-- Sidebar -->
        <aside class="account-sidebar">
            <div class="account-avatar-wrap">
                <div class="account-avatar">
                    <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.png'): ?>
                        <img src="uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="account-name"><?= htmlspecialchars($user['name']) ?></div>
                <span class="account-role"><?= $role_labels[$user['role']] ?? $user['role'] ?></span>
            </div>
            <nav class="account-nav">
                <a href="?tab=profile" class="<?= $active_tab === 'profile' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Mon profil
                </a>
                <a href="?tab=password" class="<?= $active_tab === 'password' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Mot de passe
                </a>
                <?php if ($user['role'] === 'agent'): ?>
                <a href="agent/index.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    Espace agent
                </a>
                <?php endif; ?>
                <a href="logout.php" style="color:var(--red);opacity:.8">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Déconnexion
                </a>
            </nav>
        </aside>

        <!-- Main content -->
        <div>
            <?php if ($active_tab === 'profile'): ?>

            <!-- Info résumé -->
            <div class="form-section" style="margin-bottom:20px;">
                <div class="block-title">Informations actuelles</div>
                <div class="account-info-grid">
                    <div class="account-info-item">
                        <div class="account-info-label">Statut</div>
                        <div class="account-info-value">
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-green">● Actif</span>
                            <?php else: ?>
                                <span class="badge badge-red">● Inactif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="account-info-item">
                        <div class="account-info-label">Membre depuis</div>
                        <div class="account-info-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    <div class="account-info-item">
                        <div class="account-info-label">Téléphone</div>
                        <div class="account-info-value"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
                    </div>
                    <div class="account-info-item">
                        <div class="account-info-label">Ville</div>
                        <div class="account-info-value"><?= htmlspecialchars($user['city'] ?? '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- Formulaire profil -->
            <div class="form-section">
                <div class="block-title">Modifier mon profil</div>
                <form method="post">
                    <input type="hidden" name="action" value="update_profile">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
                        <div class="form-group" style="grid-column:span 2">
                            <label>Nom complet <span class="req">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label>Adresse</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn">Enregistrer les modifications</button>
                </form>
            </div>

            <?php else: ?>

            <!-- Formulaire mot de passe -->
            <div class="form-section">
                <div class="block-title">Changer le mot de passe</div>
                <form method="post" style="max-width:400px;">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label>Mot de passe actuel <span class="req">*</span></label>
                        <input type="password" name="old_password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe <span class="req">*</span></label>
                        <input type="password" name="new_password" placeholder="Min. 6 caractères" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe <span class="req">*</span></label>
                        <input type="password" name="confirm_password" placeholder="Répétez le mot de passe" required>
                    </div>
                    <button type="submit" class="btn">Mettre à jour le mot de passe</button>
                </form>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>