<?php
session_start();

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
        'title' => 'Stored Cross-site Scripting',
        'note'  => "\nCall alert() by adding the following HTML and JavaScript to a file called 'poc.html'\n\nUpload the file then click 'View/Download to observe exection",
        'text'  => '<script>alert(1)</script>',
    ],
    [
        'title' => 'Basic Remote Code Execution',
        'note'  => "\nCall phpinfo() by adding the following code to a file called 'poc.php'\n\nUpload the file then click 'View/Download to observe exection",
        'text'  => '<?php phpinfo();?>',
    ],
];


/* =====================
   UPLOAD HANDLER
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = 'Upload failed';
    } elseif (!is_writable($uploadDir)) {
        $msg = 'Uploads directory not writable';
    } else {

        $orig = basename((string)$_FILES['file']['name']);
        $orig = str_replace(["\0", "/", "\\"], "", $orig);

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir.$orig)) {

            $_SESSION['raw_name'] = $orig;
            $_SESSION['stored_for_display'] = $orig;
            $_SESSION['current_file'] = $orig;

            unset($_SESSION['decoded_name']);

            $msg = 'Upload OK';
        } else {
            $msg = 'Upload save failed';
        }
    }
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

$current = $_SESSION['current_file'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Unsafe Upload Handling</title>
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
    <h2>Unsafe Upload: RCE and Stored XSS</h2>
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

    <?php if ($current): ?>
      <h3>Uploaded File</h3>
      <pre><?= $current ?></pre>
      <a href="<?= $uploadUrl . rawurlencode($current) ?>" target="_blank">View / Download Uploaded File</a>
    <?php endif; ?>-

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
    Demonstration of Remote Code Execution and Stored Cross-site scripting due to Insecure File Upload.
  </div>
  </div>

</div>
<br>
<a class="whitetext" href="/">Home</a></div>

<script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>
