<?php
$id = "";

$id= $_REQUEST["id"] ?? "";

$payload_templates = [
    [
        'title' => 'Try inserting any random string',
        'note'  => "\nInsert the following string and click apply\n\nObserve the structural syntax your input is reflected in and that you control all text within the script",
        'text'  => "d34db33f",
    ],
    [
        'title' => 'Try inserting an apostrophe followed by a string',
        'note'  => "\nIn devtools console observe the error message generated\n\nYou can rely on these debugging messages to aid in writing an XSS payload that will execute properly",
        'text'  => "'d34db33f",
    ],
    [
        'title' => 'Try inserting a basic XSS Proof of Concept (Poc)',
        'note'  => "\nObserve execution of your injected JavaScript",
        'text'  => "alert(1)",
    ],

];

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// emulating some html encoders that don't encode single quotes since they aren't dangerous to html 
function encode(string $input): string
{
    return str_replace(
        ['<', '>'],
        ['\\x3C', '\\x3E'],
        $input
    );
}
 // the javascript that gets reflected into the UI 
$callLineJs = "";
if ($id !== "") {
    // this returns only user input directly into the script tags
    $callLineJs =
        '' . $id . '';
}


//Build instructions to aid in debugging via the UI.
$scriptBlockText = "";
$headerBlockText = "";
if ($callLineJs !== "") {
	$headerBlockText = "<h3>Open Devtools</h3>\n";
	$scriptBlockText = " <pre>\n";
    $scriptBlockText .= "Press <b>F12</b>\n";
	$scriptBlockText .= "Chrome: Open <b>Elements</b>\n";
	$scriptBlockText .= "Firefox: Open <b>Inspector</b>\n";
    $scriptBlockText .= "Press <b>CTRL + f</b>\n";
    $scriptBlockText .= "Search for: <b>" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ."</b>\n";
	$scriptBlockText .= "Observe the <b>JavaScript context</b> of your <b>reflected input</b>\n";
	$scriptBlockText .= "</pre>";
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>XSS Basics</title>
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

  <!-- Header -->
  <div class="header-row">
    <h2>Cross-Site Scripting Basics</h2>
  </div>

  <!-- Input Panel -->
  <div class="input-panel">

    <form method="POST">

      <label for="id">Element ID (alphanumeric)</label>
      <input name="id" id="id" placeholder="a1s2d3f4g5h6"
      >

      <button type="submit">Apply</button>

    </form>


<div id="a1s2d3f4g5h6"></div>
  </div>

  <!-- Target -->
  <h3>Waiting to Activate FluxMode...</h3>
  
   <?php echo $headerBlockText; ?>
   <?php echo $scriptBlockText; ?>

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
	  Demonstration of reflected XSS into a script element and how to use the browser's developer console to develop an XSS Proof of Concept.
    </div>
</div>
</div>
  <br>
  <a class="whitetext" href="/">Home</a>
  </div>
  <script>
<?php if ($id !== ""): ?>
  // Reflect mishandled user input directly into script tags:
  <?php echo  encode($id) ?>
<?php endif; ?>
</script>

<script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>


