<?php
// Accepts GET: ?filter={"types":["stream","baseline"]}

declare(strict_types=1);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$raw_filter = isset($_GET['filter']) ? (string)$_GET['filter'] : '';

$payload_templates = [
    [
        'title' => 'Try inserting the application\'s expected input formatting',
        'note'  => "\nInsert the following string and click apply\n\nIn developer tools, observer that no errors are returned",
        'text'  => '{"types":["configurations","baseline","changess"]}',
    ],
    [
        'title' => 'Try inserting any random string',
        'note'  => "\nIn devtools console observe the error message generated\n\nYou can rely on these debugging messages to aid in writing an XSS payload that will execute properly",
        'text'  => "d34db33f",
    ],
	    [
        'title' => 'Set a breakpoint in the DOMxss.js file',
        'note'  => "\nIn devtools Sources(chrome) or Debugger(firefox) tab find the script called DOMxss.js and click 23 to set a breakpoint\n\nIn the application click apply\n\nHover over 'obj' in line 23 to observe the value you injected",
        'text'  => "d34db33f",
    ],
    [
        'title' => 'Try inserting a basic XSS Proof of Concept (Poc)',
        'note'  => "\nRemove the breakpoint set above by clicking 23 again\n\nInsert the payload and click apply\nObserve execution of your injected JavaScript",
        'text'  => "alert(1)",
    ],

];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>XSS JavaScript Injection</title>
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

    <!-- 1) App panel -->
    <div class="app-container panel">
      <div class="header-row">
        <h2>Cross-site Scripting via JavaScript Injection</h2>
      </div>

      <div class="input-panel">
        <label for="filter">User input</label>
        <input id="filter" type="text"
               placeholder='{"types":["configurations","baseline","changes"]}'
               value="<?php echo h($raw_filter); ?>">

        <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
          <button type="button" id="applyBtn">Apply</button>
        </div>

        <p class="note" style="margin-top:12px;">
          Expected format: <br><code>{"types":["configurations","baseline","changess"]}</code>
        </p>
		
		      <div class="header-row">
        <h2>Output</h2>
      </div>

        <p class="note"><strong>Raw filter (from URL):</strong></p>
        <pre id="rawOut"></pre>

        <p hidden class="note" id="statusOut" style="margin-top:10px;"></p>
     

      </div>
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

<div class="text-container">
<div class="input-panel textborder">
	      This demo parses JSON values from <code>filter</code> via <code>location.search</code> and passes user controllable input to <code>eval()</code> without input validation or sanitization.
    </div>
	</div>

  </div>
  <br>
  <a class="whitetext" href="/">Home</a></div>

<script src="/shared/scripts/DOMxss.js"></script>
<script src="/shared/scripts/payloadsButton.js"></script>

</body>
</html>
