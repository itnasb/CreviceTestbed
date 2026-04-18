<?php
session_start();

function normalize_ext(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $ext);
}

function detect_mime(string $tmpPath): string {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    return $fi->file($tmpPath) ?: 'application/octet-stream';
}

/**
 * Returns [ok(bool), error(string|null), safe_ext(string), safe_name(string)]
 */
function validate_upload_whitelist(array $file): array {
    // Extension allowlist -> acceptable MIME types
    // (Keep this conservative. You can expand later.)
    $allow = [
        // Images (avoid SVG due to scriptability)
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],

        // Documents
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'json' => ['application/json', 'text/plain'],

        // Office (common + typically safe from “stored XSS” in the browser, but macros are a separate concern)
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],

    ];

    // Hard blocklist of risky extensions (defense in depth)
    $blockExt = [
        'php','phtml','php3','php4','php5','phar',
        'html','htm','xhtml','svg','xml',
        'js','mjs',
        'css',
        'exe','dll','so','bin','sh','bash','zsh','bat','cmd','ps1',
        'cgi','pl','py','rb','zip',
    ];

    $origName = (string)($file['name'] ?? '');
    $tmpPath  = (string)($file['tmp_name'] ?? '');

    if ($origName === '' || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [false, 'Invalid upload', '', ''];
    }

    $ext = normalize_ext($origName);
    if ($ext === '') {
        return [false, 'Missing file extension', '', ''];
    }
    if (in_array($ext, $blockExt, true)) {
        return [false, 'File type not allowed', '', ''];
    }
    if (!isset($allow[$ext])) {
        return [false, 'File type not allowed', '', ''];
    }

    $mime = detect_mime($tmpPath);
    if (!in_array($mime, $allow[$ext], true)) {
        return [false, 'MIME type not allowed', '', ''];
    }

    // Minimal filename safety for Linux (avoid path tricks / NUL)
    $safeName = basename($origName);
    $safeName = str_replace(["\0", "/", "\\"], "", $safeName);

    // Optional: normalize weird whitespace
    $safeName = preg_replace('/\s+/', ' ', $safeName);
    $safeName = trim($safeName);

    return [true, null, $ext, $safeName];
}


$uploadDir = __DIR__ . '/uploads/';
$uploadUrl = 'uploads/';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function risky_decode(string $s): string {
    return html_entity_decode($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$msg = '';

$payload_templates = [

    [
        'title' => 'Encoded Script Tag',
        'note'  => "\nUse the HTML entity encoded script tag as a filename and upload it.\n\nObserve reflection before and after Save Again.",
        'text'  => '&lt;script&gt;alert(1)&lt;/script&gt;.txt',
    ],
];


/* =====================
   UPLOAD HANDLER
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = 'Upload failed';
    } else {
        [$ok, $err, $ext, $safeName] = validate_upload_whitelist($_FILES['file']);

        if (!$ok) {
            $msg = $err;
        } else {
            // Store using user-submitted filename (your current requirement)
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                $_SESSION['raw_name'] = $safeName;
                $_SESSION['stored_for_display'] = $safeName;
                $_SESSION['current_file'] = $safeName;
                unset($_SESSION['decoded_name']);
                $msg = 'Upload OK';
            } else {
                $msg = 'Upload save failed';
            }
        }
    }
}


/* =====================
   SAVE AGAIN
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_again') {

    $userEdited = (string)($_POST['display_name'] ?? '');

    $storedForDisplay = $userEdited;
    $decoded = $storedForDisplay;

    $_SESSION['stored_for_display'] = $storedForDisplay;
    $_SESSION['decoded_name'] = $decoded;

    $msg = 'Save again completed';
}


/* =====================
   LIST FILES
===================== */
$files = [];

if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (!is_file($uploadDir.$f)) continue;
        $files[] = $f;
    }
}

$raw     = $_SESSION['raw_name'] ?? '';
$stored  = $_SESSION['stored_for_display'] ?? '';
$decoded = $_SESSION['decoded_name'] ?? '';
$current = $_SESSION['current_file'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>XSS Encoding Regression</title>
  <link rel="stylesheet" href="/shared/css/theme.css">
  <link rel="stylesheet" href="/shared/css/header.css">
</head>

<body>
<div class="page-wrap">
 <a href="/" class="ctb-home">
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
  </a>
<div class="layout-row">

  <!-- MAIN APP -->
  <div class="app-container panel">

    <div class="header-row">
      <h2>Cross-Site Scripting: Encoding Regression</h2>
    </div>

    <div class="input-panel">
      <div class="whitetext"><?= h($msg) ?></div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">

        <label>Upload File</label>
        <input type="file" name="file" required>

        <button type="submit">Upload</button>
      </form>
    </div>

    <?php
      $preloadSource = $_SESSION['stored_for_display'] ?? ($_SESSION['raw_name'] ?? '');
      $preloadDecoded = html_entity_decode($preloadSource);
    ?>

    <div class="input-panel">
      <form method="POST">
        <input type="hidden" name="action" value="save_again">

        <label>Display Name (Decoded)</label>
        <input
          type="text"
          name="display_name"
          value="<?= h($preloadDecoded) ?>"
          placeholder="(edit me, then Save Again)"
          autocomplete="off"
        >

        <button type="submit">Save Again</button>
      </form>
    </div>

    <h3>Observed Values</h3>
    <pre>Raw: <?= $raw ?></pre>
    <pre>Stored: <?= h($stored) ?></pre>
    <pre>Decoded: <?= $decoded ?></pre>

    <?php if ($current): ?>
      <h3>Current File</h3>
      <pre><?= $current ?></pre>

    <?php endif; ?>

  </div>

  <!-- EXAMPLE PAYLOADS -->
 <div class="payloads-container panel">

  <div class="header-row">
    <h2>Example Payloads</h2>
    <button type="button" id="toggleAllPayloads">Show all</button>
  </div>

  <div class="input-panel" style="margin-bottom:0;">

    <?php foreach ($payload_templates as $idx => $p): ?>

      <div class="payload-block">

        <div class="payload-body" id="payload-<?= $idx ?>">

          <p class="note">
            <strong><?= h($p['title']) ?>:</strong>
            <?= nl2br(h($p['note'])) ?>
          </p>

          <pre><?= h($p['text']) ?></pre>

        </div>

      </div>

    <?php endforeach; ?>

  </div>
</div>


  <!-- DESCRIPTION -->
  <div class="text-container">
  <div class="input-panel textborder">
    Demonstration of inconsistent canonicalization where filenames are uploaded, reflected, decoded, and reused.
  </div>
  </div>

</div>
<br>
<a class="whitetext" href="/">Home</a>
</div>
<script src="/shared/scripts/payloadsButton.js"></script>

</body>
</html>
