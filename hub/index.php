<?php
// hub/index.php
// Web App Security Testing Demo Hub (auto-discover labs + per-lab app.json metadata)
// Discovery rule: any subfolder under /labs containing index.php is a lab.
// Optional metadata file per lab: app.json { "name": "...", "desc": "...", "order": 10, "version": "..." }

$hubDir  = __DIR__;
$rootDir = realpath($hubDir . '/../labs') ?: ($hubDir . '/../labs'); // labs/ lives next to hub/

$hubVersion  = '';
$hubMetaFile = $hubDir . DIRECTORY_SEPARATOR . 'app.json';

$skipDirs = [
  '.', '..',
  '.git', '.github', '.idea', '.vscode',
  'assets', 'css', 'js', 'img', 'images', 'fonts',
  'vendor', 'node_modules', 'archive',
];

// Safety limits for metadata (keeps the app resilient)
const MAX_NAME_LEN = 80;
const MAX_DESC_LEN = 200;

function safe_str($v, int $maxLen): ?string {
  if (!is_string($v)) return null;
  $v = trim($v);
  if ($v === '') return null;

  // mbstring is present in your Docker image, but may not be on host PHP when using `php -S`.
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($v) > $maxLen) $v = mb_substr($v, 0, $maxLen);
  } else {
    if (strlen($v) > $maxLen) $v = substr($v, 0, $maxLen);
  }

  return $v;
}

function safe_int($v): ?int {
  if (is_int($v)) return $v;
  if (is_string($v) && preg_match('/^-?\d+$/', $v)) return (int)$v;
  if (is_float($v)) return (int)$v;
  return null;
}

// Hub version from hub/app.json (optional)
if (is_file($hubMetaFile)) {
  $raw = @file_get_contents($hubMetaFile);
  if ($raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
      $hubVersion = safe_str($decoded['version'] ?? null, 40) ?: '';
    }
  }
}

$apps = [];

if (is_dir($rootDir)) {
  foreach (scandir($rootDir) as $entry) {
    if (in_array($entry, $skipDirs, true)) continue;
    if ($entry !== '' && $entry[0] === '.') continue;

    $fullPath = $rootDir . DIRECTORY_SEPARATOR . $entry;

    if (!is_dir($fullPath)) continue;
    if (!is_file($fullPath . DIRECTORY_SEPARATOR . 'index.php')) continue;

    $slug = $entry;

    // New URL scheme: /labs/<slug>/
    $href = '/labs/' . rawurlencode($slug) . '/';

    // Optional metadata: app.json
    $metaFile = $fullPath . DIRECTORY_SEPARATOR . 'app.json';
    $cfg = null;

    if (is_file($metaFile)) {
      $raw = @file_get_contents($metaFile);
      if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
          $cfg = $decoded;
        }
      }
    }

    $friendly = safe_str($cfg['name'] ?? null, MAX_NAME_LEN)
      ?? ucwords(str_replace(['-', '_'], ' ', $slug));

    $desc = safe_str($cfg['desc'] ?? null, MAX_DESC_LEN)
      ?? ("PHP app in /labs/{$slug}/");

    $order = safe_int($cfg['order'] ?? null);
    if ($order === null) $order = 999;

    $apps[] = [
      'slug'     => $slug,
      'href'     => $href,
      'name'     => $friendly,
      'desc'     => $desc,
      'order'    => $order,
      'has_meta' => is_array($cfg),
    ];
  }
}

// Sort by explicit order, then name as fallback
usort($apps, function ($a, $b) {
  if ($a['order'] === $b['order']) {
    return strcasecmp($a['name'], $b['name']);
  }
  return $a['order'] <=> $b['order'];
});
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Security Testing Labs</title>

  <link rel="stylesheet" href="/shared/css/theme.css">
  <link rel="stylesheet" href="/shared/css/hub.css">
  <link rel="stylesheet" href="/shared/css/header.css">
</head>

<body>
<div class="page-wrap">
  <div class="hub-wrap">

 
    <div class="ctb-header headerpanel" role="banner">
      <div class="ctb-inner">
        <img class="ctb-shield" src="/shared/images/shield.png" alt="" aria-hidden="true" />
        <div class="ctb-text">
          <div class="ctb-title">
            <div class="crevice">CREVICE</div>
            <div class="testbed">TESTBED</div>
          </div>
        </div>
        <div class="ctb-tag">Exploit WebApp Vulnerabilities</div>
      </div>
    </div>
 
    <div class="panel hub-panel">
      <div class="input-panel">
        <?php if (!is_dir($rootDir)): ?>
          <p class="empty">
            Labs directory not found: <code><?php echo htmlspecialchars((string)$rootDir, ENT_QUOTES, 'UTF-8'); ?></code><br><br>
            Expected: <code>labs/</code> next to <code>hub/</code>.
          </p>
        <?php elseif (count($apps) === 0): ?>
          <p class="empty">
            No apps found under <code>labs/</code>.<br><br>
            Create a folder like <code>labs/my-app/index.php</code> and refresh.
          </p>
        <?php else: ?>
          <div class="apps-grid">
            <?php foreach ($apps as $app): ?>
              <a class="app-tile" href="<?php echo htmlspecialchars($app['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="app-title">
                  <span><?php echo htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <p class="app-desc">
                  <?php echo htmlspecialchars($app['desc'], ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <?php if (!$app['has_meta']): ?>
                  <div class="meta-hint">
                    Tip: add <code>/labs/<?php echo htmlspecialchars($app['slug'], ENT_QUOTES, 'UTF-8'); ?>/app.json</code>
                    for a custom name/desc/order
                  </div>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="small muted">
      <br>
      <?php if ($hubVersion !== ''): ?>
        Version: v<?php echo htmlspecialchars($hubVersion, ENT_QUOTES, 'UTF-8'); ?>
      <?php endif; ?>
    </div>

  </div>
</div>
</body>
</html>
