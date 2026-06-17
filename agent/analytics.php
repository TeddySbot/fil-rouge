<?php
session_start();
require '../config/database.php';

use App\Repository\AgencyRepository;

/* ── Garde-fou : réservé aux agents ── */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$agent_id = (int) $_SESSION['user_id'];

/* ── Agence de l'agent (couche POO) ── */
$agency = (new AgencyRepository($pdo))->findByAgent($agent_id);
if ($agency === null) {
    header('Location: index.php');
    exit;
}

/* ── Configuration du module Python ──
 * Sur XAMPP/Windows l'exécutable est généralement « python ».
 * Adaptez si nécessaire (« py », « python3 », ou un chemin absolu). */
$pythonBin = 'python';
$scriptDir = realpath(__DIR__ . '/../analytics');
$script    = $scriptDir . DIRECTORY_SEPARATOR . 'analyze.py';
$outputDir = $scriptDir . DIRECTORY_SEPARATOR . 'output';
$reportFile = $outputDir . DIRECTORY_SEPARATOR . 'report.json';

$run_output = null;
$run_error  = null;

/* ── Lancement de l'analyse (sur clic) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    if (!is_file($script)) {
        $run_error = "Script introuvable : $script";
    } elseif (!function_exists('shell_exec')) {
        $run_error = "La fonction shell_exec() est désactivée sur ce serveur.";
    } else {
        $cmd = escapeshellarg($pythonBin) . ' ' . escapeshellarg($script)
             . ' --agency-id ' . $agency->id
             . ' --output ' . escapeshellarg($outputDir)
             . ' 2>&1';
        $run_output = shell_exec($cmd);
        if ($run_output === null) {
            $run_error = "Impossible d'exécuter Python. Vérifiez que « $pythonBin » est installé et dans le PATH, "
                       . "et que les dépendances sont installées (pip install -r analytics/requirements.txt).";
        }
    }
}

/* ── Lecture du rapport ── */
$report = null;
if (is_file($reportFile)) {
    $report = json_decode(file_get_contents($reportFile), true);
}

$money = fn($v) => number_format((float) $v, 0, ',', ' ') . ' €';
$asset = fn($name) => '../analytics/output/' . $name . '?t=' . (is_file($outputDir . '/' . $name) ? filemtime($outputDir . '/' . $name) : time());

require '../includes/header.php';
?>

<div class="container">
  <div class="dash-header">
    <div>
      <div class="page-eyebrow"><?= htmlspecialchars($agency->name) ?></div>
      <h1 style="margin-bottom:0">Analyse de données</h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <form method="post" style="margin:0">
        <button type="submit" name="run" value="1" class="btn btn-sm">↻ Lancer / actualiser l'analyse</button>
      </form>
      <a href="index.php" class="btn btn-secondary btn-sm">← Tableau de bord</a>
    </div>
  </div>

  <p style="font-size:13px;color:var(--muted);margin-bottom:24px;max-width:680px;">
    Rapports générés par un module <strong style="color:var(--text)">Python</strong>
    (pandas / numpy / matplotlib) à partir des données de votre agence :
    rapport de ventes, biens populaires, prévisions et zones intéressantes.
  </p>

  <?php if ($run_error): ?>
    <div class="error"><?= htmlspecialchars($run_error) ?></div>
  <?php endif; ?>

  <?php if (is_array($report) && ($report['success'] ?? true) === false): ?>
    <div class="error">
      Erreur du module d'analyse : <?= htmlspecialchars($report['error'] ?? 'inconnue') ?>
    </div>
  <?php endif; ?>

  <?php if (!$report): ?>
    <div class="form-section" style="text-align:center;">
      <div style="font-size:40px;margin-bottom:10px;">📈</div>
      <p style="color:var(--text);font-weight:600;margin-bottom:6px;">Aucune analyse disponible pour le moment.</p>
      <p style="color:var(--muted);font-size:13px;margin-bottom:16px;">
        Cliquez sur « Lancer l'analyse » pour générer le premier rapport.
      </p>
      <form method="post"><button type="submit" name="run" value="1" class="btn">Lancer l'analyse</button></form>
    </div>

  <?php elseif (($report['success'] ?? true) !== false): ?>

    <?php $k = $report['kpis'] ?? []; ?>

    <!-- KPIs -->
    <div class="block-title">Vue d'ensemble</div>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:14px;">
      <div class="stat-card"><div class="stat-val soft"><?= (int)($k['total_properties'] ?? 0) ?></div><div class="stat-lbl">Biens analysés</div></div>
      <div class="stat-card"><div class="stat-val green"><?= (int)($k['completed_sales'] ?? 0) ?></div><div class="stat-lbl">Ventes finalisées</div></div>
      <div class="stat-card"><div class="stat-val"><?= $money($k['total_revenue'] ?? 0) ?></div><div class="stat-lbl">Chiffre d'affaires</div></div>
      <div class="stat-card"><div class="stat-val blue"><?= (int)($k['total_views'] ?? 0) ?></div><div class="stat-lbl">Vues cumulées</div></div>
    </div>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:32px;">
      <div class="stat-card"><div class="stat-val green"><?= (int)($k['available'] ?? 0) ?></div><div class="stat-lbl">Disponibles</div></div>
      <div class="stat-card"><div class="stat-val"><?= (int)($k['sold'] ?? 0) ?></div><div class="stat-lbl">Vendus</div></div>
      <div class="stat-card"><div class="stat-val"><?= $money($k['avg_price'] ?? 0) ?></div><div class="stat-lbl">Prix moyen</div></div>
      <div class="stat-card"><div class="stat-val"><?= number_format($k['avg_price_per_m2'] ?? 0, 0, ',', ' ') ?> €</div><div class="stat-lbl">Prix moyen / m²</div></div>
    </div>

    <?php if (!empty($report['warnings'])): ?>
      <div class="success" style="background:rgba(212,168,67,.08);border-color:rgba(212,168,67,.25);color:var(--gold);">
        <?= htmlspecialchars(implode(' ', $report['warnings'])) ?>
      </div>
    <?php endif; ?>

    <!-- Graphiques -->
    <div class="block-title">Rapports visuels</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:32px;">
      <?php foreach (($report['charts'] ?? []) as $name => $file): ?>
        <div class="form-section" style="margin:0;padding:14px;">
          <img src="<?= htmlspecialchars($asset($file)) ?>" alt="<?= htmlspecialchars($name) ?>"
               style="width:100%;border-radius:8px;display:block;">
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Prévisions -->
    <?php $fc = $report['forecast'] ?? []; ?>
    <div class="block-title">Prévisions de ventes</div>
    <div class="form-section" style="margin:0 0 32px;">
      <?php if (!empty($fc['next_months'])): ?>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px;">
          Méthode : <strong style="color:var(--text)"><?= htmlspecialchars($fc['method'] ?? '') ?></strong>
          <?php if (isset($fc['trend'])): ?>· tendance :
            <strong style="color:<?= $fc['trend']==='hausse'?'var(--green)':($fc['trend']==='baisse'?'var(--red)':'var(--soft)') ?>">
              <?= htmlspecialchars($fc['trend']) ?></strong>
          <?php endif; ?>
        </p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Mois</th><th>Ventes prévues</th><th>CA prévu</th></tr></thead>
            <tbody>
              <?php foreach ($fc['next_months'] as $m): ?>
                <tr>
                  <td><?= htmlspecialchars($m['month']) ?></td>
                  <td><strong style="color:var(--text)"><?= (int)$m['predicted_sales'] ?></strong></td>
                  <td style="color:var(--gold);font-weight:600;"><?= $money($m['predicted_revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:13px;margin:0;">
          <?= htmlspecialchars($fc['reason'] ?? "Prévision indisponible (données insuffisantes).") ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Zones intéressantes -->
    <?php if (!empty($report['zones'])): ?>
    <div class="block-title">Zones intéressantes (prix moyen au m²)</div>
    <div class="table-wrap" style="margin-bottom:32px;">
      <table>
        <thead><tr><th>Ville</th><th>Prix moyen / m²</th><th>Médiane / m²</th><th>Prix moyen</th><th>Biens</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($report['zones'], 0, 10) as $z): ?>
            <tr>
              <td style="color:var(--text);font-weight:500;"><?= htmlspecialchars($z['city'] ?: '—') ?></td>
              <td style="color:var(--gold);font-weight:600;"><?= number_format($z['avg_price_m2'], 0, ',', ' ') ?> €</td>
              <td><?= number_format($z['median_price_m2'], 0, ',', ' ') ?> €</td>
              <td><?= $money($z['avg_price']) ?></td>
              <td><?= (int)$z['count'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Biens populaires -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
      <div>
        <div class="block-title">Plus consultés</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Bien</th><th>Ville</th><th>Vues</th></tr></thead>
            <tbody>
              <?php foreach (($report['popular_by_views'] ?? []) as $p): ?>
                <tr>
                  <td style="color:var(--text);"><?= htmlspecialchars($p['title']) ?></td>
                  <td><?= htmlspecialchars($p['city']) ?></td>
                  <td><strong style="color:var(--text)"><?= (int)$p['views'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($report['popular_by_views'])): ?><tr><td colspan="3" style="color:var(--muted)">—</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div>
        <div class="block-title">Plus mis en favori</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Bien</th><th>Ville</th><th>Favoris</th></tr></thead>
            <tbody>
              <?php foreach (($report['popular_by_favorites'] ?? []) as $p): ?>
                <tr>
                  <td style="color:var(--text);"><?= htmlspecialchars($p['title']) ?></td>
                  <td><?= htmlspecialchars($p['city']) ?></td>
                  <td><strong style="color:var(--gold)"><?= (int)$p['favorites'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($report['popular_by_favorites'])): ?><tr><td colspan="3" style="color:var(--muted)">—</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p style="font-size:12px;color:var(--muted);">
      Dernière génération : <?= htmlspecialchars($report['generated_at'] ?? '—') ?>
      · <?= (int)($report['data_counts']['properties'] ?? 0) ?> biens,
      <?= (int)($report['data_counts']['transactions'] ?? 0) ?> transactions analysés.
    </p>
  <?php endif; ?>

  <?php if ($run_output && (($report['success'] ?? true) === false || !$report)): ?>
    <details style="margin-top:18px;">
      <summary style="cursor:pointer;color:var(--muted);font-size:12px;">Sortie technique du script</summary>
      <pre style="white-space:pre-wrap;background:var(--surf2);padding:12px;border-radius:8px;font-size:12px;color:var(--soft);overflow:auto;"><?= htmlspecialchars($run_output) ?></pre>
    </details>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
