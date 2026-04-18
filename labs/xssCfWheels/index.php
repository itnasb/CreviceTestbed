<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Environment Reload -> Reflected XSS Lab
|--------------------------------------------------------------------------
| This lab is intentionally vulnerable.
| - Starts at ?reload=production
| - Any non-production environment enables a raw debug footer
| - Footer reflects the full request URI and all query parameters unsafely
|--------------------------------------------------------------------------
*/

// Force the student to land on ?reload=production so the interesting
// parameter is visible immediately.
if (!isset($_GET['reload'])) {
    header('Location: ?reload=production');
    exit;
}

$allowedEnvironments = ['design', 'development', 'test', 'production'];
$requestedReload     = (string)($_GET['reload'] ?? 'production');

if (in_array($requestedReload, $allowedEnvironments, true)) {
    $environment = $requestedReload;
} else {
    $environment = 'production';
}


$payload_templates = [
    [
        'title' => 'The reload paramter determines what environment is loaded',
        'note'  => "\n?reload=production",
        'text'  => "?reload=production",
    ],
    [
        'title' => 'Try changing to the development environment, observe that details from your request to the app are reflected back',
        'note'  => "\n?reload=development",
        'text'  => "?reload=development",
    ],
    [
        'title' => 'Try adding a random parameter name and value and observe it is reflected',
        'note'  => "\n?reload=development&note=hello",
        'text'  => "?reload=development&note=hello",
    ],
    [
        'title' => 'Make the value of the additional paramter an XSS PoC',
        'note'  => "\n?reload=development&note=<script>alert(1)</script>",
        'text'  => "?reload=development&note=<script>alert(1)</script>",
    ],
];


function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$showDebugFooter = ($environment !== 'production');

// Benign page data

$pageHeading = 'Request Tracking Dashboard';
$pageSub     = 'Environment Reload Parameter';
$statusMsg   = 'All request pipelines are operating normally.';
$ticketId    = 'REQ-' . random_int(1400, 1899);
$owner       = 'Operations Queue';
$region      = 'US-West';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Cross-Site Scripting CFWheels</title>
  <link rel="stylesheet" href="/shared/css/theme.css?asd12fwew">
  <link rel="stylesheet" href="/shared/css/header.css">
  <style>



  </style>
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
<div class="app-container panel">
  <!-- Header -->
  <div class="header-row">
    <h2>Cross-Site Scripting CFWheels</h2>
  </div>

   
      <p class="muted"><?= htmlspecialchars($pageSub, ENT_QUOTES, 'UTF-8') ?></p>
      <span class="env-chip">Current Environment: <?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></span>
<p></p>
      <div class="input-panel">
      
          <h2 class="whitetext">Request Summary</h2>
          <p><strong>Request ID:</strong> <?= htmlspecialchars($ticketId, ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Status:</strong> <?= htmlspecialchars($statusMsg, ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Owner:</strong> <?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Region:</strong> <?= htmlspecialchars($region, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
</div>
<!-- Example payloads panel -->
    <div class="payloads-container panel">
      <div class="header-row">
        <h2>Example Payloads</h2>
        <button type="button" id="toggleAllPayloads">Show all</button>
      </div>

      <div class="input-panel" style="margin-bottom:0;">

        <?php foreach ($payload_templates as $idx => $p): ?>
          <div class="payload-block">
            <div class="payload-body" id="payload-<?php echo $idx; ?>">
              <p class="note">
                <strong><?php echo h($p['title']); ?>:</strong>
                <?php echo nl2br(h($p['note'])); ?>
              </p>
              <pre><?php echo h($p['text']); ?></pre>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
 
  <div class="text-container">
  <div class="input-panel textborder">
            Demonstration of CFWheels environment behavior introducig a Cross-site Scripting sink. Wheels applications can run in different
            environments such as <strong>design</strong>, <strong>development</strong>, <strong>test</strong>, and
            <strong>production</strong>. In real deployments, production should be locked down so users cannot
            influence environment behavior. In this lab, the <strong>reload</strong> URL parameter controls which
            environment is loaded. Any non-production value enables a debug-style footer that reflects request
            data unsafely.
          </p>




</div></div></div>



    <?php if ($showDebugFooter): ?>
      <div class="debug-footer">
        <h3>Application Diagnostics</h3>
        <div class="debug-grid">
          <div>
            <strong>Environment:</strong>
            <?= $environment ?>
          </div>

          <div>
            <strong>Request URL:</strong><br>
            <span class="raw-uri"><?= $_SERVER['REQUEST_URI'] ?? '' ?></span>
          </div>

          <div class="debug-table-wrap">
            <h4>Request Parameters</h4>
            <table class="debug-table">
              <thead>
                <tr>
                  <th>Parameter</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
              <?php if (!empty($_GET)): ?>
                <?php foreach ($_GET as $key => $value): ?>
                  <tr>
                    <td><?= $key ?></td>
                    <td><?= is_array($value) ? implode(', ', $value) : $value ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="2">No parameters supplied.</td>
                </tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>
  <script src="/shared/scripts/payloadsButton.js"></script>
</div>
</body>
</html>