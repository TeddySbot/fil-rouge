<?php
session_start();
require 'config/database.php';

// Récupérer l'ID de l'agence depuis l'URL
$agency_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agency_id) {
    header('Location: index.php');
    exit;
}

// Récupérer les infos de l'agence
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$agency_id]);
$agency = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agency) {
    header('Location: index.php');
    exit;
}

// Récupérer les agents de l'agence
$stmt2 = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.profile_image, u.city, u.is_active, aa.joined_at
    FROM users u
    INNER JOIN agency_agents aa ON aa.agent_id = u.id
    WHERE aa.agency_id = ? AND u.role = 'agent'
    ORDER BY aa.joined_at ASC
");
$stmt2->execute([$agency_id]);
$agents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>



<div class="ag-page">
  <div class="ag-container">

    <!-- Breadcrumb -->
    <nav class="ag-breadcrumb">
      <a href="index.php">Accueil</a>
      <span class="sep">›</span>
      <a href="agencies.php">Agences</a>
      <span class="sep">›</span>
      <span class="current"><?= htmlspecialchars($agency['name']) ?></span>
    </nav>

    <!-- Hero -->
    <div class="ag-hero">
      <div class="ag-hero-top">
        <!-- Logo -->
        <div class="ag-logo-wrap">
          <?php if ($agency['logo']): ?>
            <img src="<?= htmlspecialchars($agency['logo']) ?>" alt="Logo <?= htmlspecialchars($agency['name']) ?>">
          <?php else: ?>
            <span class="ag-logo-placeholder"><?= strtoupper(substr($agency['name'], 0, 1)) ?></span>
          <?php endif; ?>
        </div>

        <!-- Infos principales -->
        <div class="ag-hero-info">
          <div class="ag-badge">
            <span class="ag-badge-dot"></span>
            Agence active
          </div>
          <h1 class="ag-name"><?= htmlspecialchars($agency['name']) ?></h1>
          <p class="ag-city">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5z"/></svg>
            <?= htmlspecialchars($agency['city']) ?>
          </p>
        </div>

        <!-- Actions -->
        <div class="ag-hero-actions">
          <?php if ($agency['phone']): ?>
            <a href="tel:<?= htmlspecialchars($agency['phone']) ?>" class="ag-btn ag-btn-gold">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.28 19a19.45 19.45 0 0 1-6-6 19.79 19.79 0 0 1-3.93-8.56A2 2 0 0 1 3.22 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16.92z"/></svg>
              Appeler
            </a>
          <?php endif; ?>
          <?php if ($agency['email']): ?>
            <a href="mailto:<?= htmlspecialchars($agency['email']) ?>" class="ag-btn ag-btn-outline">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              Écrire
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats -->
      <div class="ag-stats">
        <div class="ag-stat">
          <div class="ag-stat-value"><?= count($agents) ?></div>
          <div class="ag-stat-label">Agent<?= count($agents) > 1 ? 's' : '' ?></div>
        </div>
        <div class="ag-stat">
          <div class="ag-stat-value"><?= date('Y') - date('Y', strtotime($agency['created_at'])) ?: '<1' ?></div>
          <div class="ag-stat-label">Ans d'activité</div>
        </div>
        <div class="ag-stat">
          <div class="ag-stat-value"><?= $agency['city'] ? htmlspecialchars(substr($agency['city'], 0, 10)) : '—' ?></div>
          <div class="ag-stat-label">Ville</div>
        </div>
        <div class="ag-stat">
          <div class="ag-stat-value"><?= $agency['is_active'] ? '✓' : '✗' ?></div>
          <div class="ag-stat-label">Statut</div>
        </div>
      </div>
    </div>

    <!-- Cards infos + description -->
    <div class="ag-cards-row">

      <!-- Coordonnées -->
      <div class="ag-card">
        <div class="ag-card-title">Coordonnées</div>
        <div class="ag-info-list">

          <div class="ag-info-row">
            <div class="ag-info-icon">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.28 19a19.45 19.45 0 0 1-6-6 19.79 19.79 0 0 1-3.93-8.56A2 2 0 0 1 3.22 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16.92z"/></svg>
            </div>
            <div class="ag-info-content">
              <div class="ag-info-label">Téléphone</div>
              <div class="ag-info-value <?= $agency['phone'] ? '' : 'empty' ?>">
                <?= $agency['phone'] ? '<a href="tel:'.htmlspecialchars($agency['phone']).'">'.htmlspecialchars($agency['phone']).'</a>' : 'Non renseigné' ?>
              </div>
            </div>
          </div>

          <div class="ag-info-row">
            <div class="ag-info-icon">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div class="ag-info-content">
              <div class="ag-info-label">Email</div>
              <div class="ag-info-value <?= $agency['email'] ? '' : 'empty' ?>">
                <?= $agency['email'] ? '<a href="mailto:'.htmlspecialchars($agency['email']).'">'.htmlspecialchars($agency['email']).'</a>' : 'Non renseigné' ?>
              </div>
            </div>
          </div>

          <div class="ag-info-row">
            <div class="ag-info-icon">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div class="ag-info-content">
              <div class="ag-info-label">Site web</div>
              <div class="ag-info-value <?= $agency['website'] ? '' : 'empty' ?>">
                <?= $agency['website'] ? '<a href="'.htmlspecialchars($agency['website']).'" target="_blank" rel="noopener">'.htmlspecialchars($agency['website']).'</a>' : 'Non renseigné' ?>
              </div>
            </div>
          </div>

          <div class="ag-info-row">
            <div class="ag-info-icon">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5z"/></svg>
            </div>
            <div class="ag-info-content">
              <div class="ag-info-label">Adresse</div>
              <div class="ag-info-value <?= $agency['address'] ? '' : 'empty' ?>">
                <?= $agency['address'] ? htmlspecialchars($agency['address'].', '.$agency['city']) : 'Non renseignée' ?>
              </div>
            </div>
          </div>

          <div class="ag-info-row">
            <div class="ag-info-icon">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="ag-info-content">
              <div class="ag-info-label">Membre depuis</div>
              <div class="ag-info-value"><?= date('d/m/Y', strtotime($agency['created_at'])) ?></div>
            </div>
          </div>

        </div>
      </div>

      <!-- Description -->
      <div class="ag-card">
        <div class="ag-card-title">À propos</div>
        <p class="ag-desc-text <?= $agency['description'] ? '' : 'empty' ?>">
          <?= $agency['description'] ? nl2br(htmlspecialchars($agency['description'])) : 'Aucune description disponible pour cette agence.' ?>
        </p>
      </div>

    </div>

    <!-- Agents -->
    <div class="ag-agents-section">
      <div class="ag-section-header">
        <h2 class="ag-section-title">Équipe des agents</h2>
        <span class="ag-count-badge"><?= count($agents) ?> agent<?= count($agents) > 1 ? 's' : '' ?></span>
      </div>

      <?php if (empty($agents)): ?>
        <div class="ag-no-agents">
          <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin: 0 auto 12px; display:block; opacity:0.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Aucun agent rattaché à cette agence pour le moment.
        </div>
      <?php else: ?>
        <div class="ag-agents-grid">
          <?php foreach ($agents as $agent): ?>
            <a href="agent_detail.php?id=<?= $agent['id'] ?>" class="ag-agent-card">
              <?php if ($agent['profile_image']): ?>
                <img src="<?= htmlspecialchars($agent['profile_image']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" class="ag-agent-avatar">
              <?php else: ?>
                <div class="ag-agent-avatar-placeholder"><?= strtoupper(substr($agent['name'], 0, 1)) ?></div>
              <?php endif; ?>
              <div class="ag-agent-name"><?= htmlspecialchars($agent['name']) ?></div>
              <div class="ag-agent-email"><?= htmlspecialchars($agent['email']) ?></div>
              <span class="ag-agent-status <?= $agent['is_active'] ? 'active' : 'inactive' ?>">
                <span class="ag-agent-status-dot"></span>
                <?= $agent['is_active'] ? 'Actif' : 'Inactif' ?>
              </span>
              <div class="ag-agent-joined">
                Depuis le <?= date('d/m/Y', strtotime($agent['joined_at'])) ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require 'includes/footer.php'; ?>