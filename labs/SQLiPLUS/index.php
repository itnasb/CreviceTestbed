<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
/*
|--------------------------------------------------------------------------
| SQLi + XSS Lab (single drop-in file)
|--------------------------------------------------------------------------
| - GET  -> renders the page
| - POST -> on success renders results in the app
| - POST -> on SQL error returns raw DB error text as the full page
|
| Intentionally unsafe for lab/training use.
|--------------------------------------------------------------------------
*/

/* -----------------------------
   1) Config & DB Connection
------------------------------*/
$db_host = 'db';
//$db_host = '127.0.0.1';
$db_user = 'sa_user';
$db_pass = 'sa_password';
$db_name = 'crevice_db';

$conn = null;

if (class_exists('mysqli')) {
    $tmp = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($tmp instanceof mysqli && !$tmp->connect_errno) {
        $conn = $tmp;
        $conn->set_charset('utf8mb4');
    }
}

/* -----------------------------
   2) Helpers
------------------------------*/
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* -----------------------------
   3) Page State
------------------------------*/
$search_term = '';
$search_executed = false;
$rows = [];
$query_used = '';

/* -----------------------------
   4) Search Handling
------------------------------*/
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['action']) &&
    $_GET['action'] === 'search'
) {
    $search_term = isset($_GET['search_term']) ? (string)$_GET['search_term'] : '';
    $search_executed = true;

    if ($conn === null) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'DB connect failed.';
        exit;
    }

    /*
     * Intentionally unsafe query construction for lab purposes.
     * % routes the request into the LIKE/reporting branch.
     */

    $query_used = "
        SELECT responder_name, response_text, aggression_score, report_date
        FROM phishing_sentiment_report
        WHERE responder_name LIKE '$search_term'
        ORDER BY responder_name, report_date DESC
    ";


// 5. Get the result set (this makes it act like $conn->query used to)
  $res = $conn->query($query_used);



    if ($res instanceof mysqli_result) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    } else {
        /*
         * Intentionally unhandled-looking SQL error for lab purposes.
         * Return raw DB error text as the full response body.
         */
        header('Content-Type: text/plain; charset=utf-8');
        echo $conn->error;
        exit;
    }
}

/* -----------------------------
   5) UI Payload Templates
------------------------------*/
$payload_templates = [
    [
        'title' => 'Insert a single quote',
        'note'  => "Observer the database\'s behavior.",
        'text'  => "'",
    ],
	    [
        'title' => 'Insert two single quotes',
        'note'  => "Observer the DB error message disappear.",
        'text'  => "''",
    ],
    [
        'title' => 'PoC Error-Based SQLi',
        'note'  => "Satisfy the SQL syntax well enough to trigger useful DB behavior.\n\nNote: The final %20 is a space character. This space is a requirement of MariaDB after comment characters \"--\". ",
        'text'  => "'and ExtractValue(1, Concat(0x3a, @@version))--%20  ",
    ],
    [
        'title' => 'Bonus XSS',
        'note'  => "When SQL syntax is satisfied and the query returns a value that evaluates to true, the search term is reflected into a script element. \n\nIn this case our query returns TRUE, satisfying the check.\n\nObserver the syntax error your injection causes in the Devtools console.\n\nTry changing 1=1 to 1=0 and observe that the errors don't occur when the response evaluates to false.",
        'text'  => "' or 1=1-- alert(1)//",
    ],
	    [
        'title' => 'Bonus XSS',
        'note'  => "The JavaScript syntax makes injecting our own JavaScript difficult.\n\nTry injecting your own script tags.\n\nObjerve that your injection doesn't execute but that you break the original script element with your closing </script> tag",
        'text'  => "' or 1=1-- <script>alert(1)</script>",
    ],
		    [
        'title' => 'Bonus XSS',
        'note'  => "Try injecting a closing script tag before injecting your script.",
        'text'  => "' or 1=1-- </script><script>alert(1)</script>",
    ],
			    [
        'title' => 'Extra Bonus XSS',
        'note'  => "Try to figure out if there's an injection syntax that doesn't require you to inject your own HTML tags.",
        'text'  => "",
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SQLi + XSS</title>
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
      <div class="header-row"><h2>Phishing Campaign Sentiment Log</h2></div>

      <div class="input-panel">
        <div class="panel">
          <h3>View User Sentiment Reports</h3>

          <form id="searchForm" method="get" action="">
            <input type="hidden" name="action" value="search" />
            <label for="search_term" >Search term</label>

            <div class="search-row">
              <input
                type="text"
                id="search_term"
                name="search_term"
                value="<?= h($search_term) ?>"
                placeholder="Enter responder name or % for wildcard search"
                autocomplete="off"
              />
              <button type="submit">Search</button>
            </div>
          </form>
        </div></div></div>


    <div class="payloads-container panel">
      <div class="header-row">
        <h2>Example Payloads</h2>
        <button type="button" id="toggleAllPayloads">Show all</button>
      </div>
      <div class="input-panel">
        <?php foreach ($payload_templates as $idx => $p): ?>
          <div class="payload-block">
            <div class="payload-body" id="payload-<?= $idx ?>" style="display:none;">
              <p><strong><?= h($p['title']) ?>:</strong> <?= nl2br(h($p['note'])) ?></p>
              <pre><?= h($p['text']) ?></pre>
            </div>
          </div>
        <?php endforeach; ?>
      </div></div>

    <div class="text-container">
      <div class="input-panel textborder">
        Demonstration of SQL injection in a reporting workflow followed by unsafe reflection into a <code>&lt;script&gt; </code>element where JavaScript is used to build a results table.
      
    </div>
  </div></div>

  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>
</div>
<?php if ($search_executed && !empty($rows)): ?>
 <div id="resultsPopover" class="popover-backdrop " aria-hidden="true" data-auto-open="1">
  <div class="popover-panel panel" role="dialog" aria-modal="true" aria-labelledby="popoverResultsTitle">
  <div class="input-panel">
    <div class="popover-header">
      <h2 id="popoverResultsTitle" class="whitetext">Search Results for:</h2>
      <div class="popover-header-actions">
        <button type="button" id="closeResultsPopoverTop" class="popover-close-x" aria-label="Close results viewer">&times;</button>
      </div>
    </div>

    <div class="popover-body">

      <div id="popoverResultsContainer" class="table-wrap results-scroll"></div>

      <div class="results-launch-row" style="margin-top:16px;">
        <button type="button" id="closeResultsPopoverBottom">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</div>
</div></div>
<?php if ($search_executed && !empty($rows)): ?>


<script>
/*
 * Intentionally unsafe script-context sink for lab purposes.
 * User input is embedded into a JavaScript object literal among
 * other properties to complicate simple injections.
 */
var TableOptions = {
  columns: ['responder_name','response_text','aggression_score','report_date'],
  rows: <?= json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  searchTerm: '<?= h($search_term) ?>',
  title: 'Search Results for: ' + '<?= $search_term ?>',
  pageSize: 25,
  sortable: true,
  theme: 'dark',
  queryMode: 'report'
};

document.addEventListener('DOMContentLoaded', function () {
  var titleEl = document.getElementById('resultsTitle');
  var metaEl = document.getElementById('resultsMeta');
  var popoverTitleEl = document.getElementById('popoverResultsTitle');
  var popoverMetaEl = document.getElementById('popoverResultsMeta');
  var popoverContainer = document.getElementById('popoverResultsContainer');

  var openBtn = document.getElementById('openResultsPopover');
  var popover = document.getElementById('resultsPopover');
  var closeTop = document.getElementById('closeResultsPopoverTop');
  var closeBottom = document.getElementById('closeResultsPopoverBottom');

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildResultsTable(options) {
    var rows = Array.isArray(options.rows) ? options.rows : [];
    var columns = Array.isArray(options.columns) && options.columns.length
      ? options.columns
      : (rows.length ? Object.keys(rows[0]) : []);

    if (!rows.length) {
      return '<div class="results-empty">No results found.</div>';
    }

    var html = '<table class="results-table"><thead><tr>';

    for (var i = 0; i < columns.length; i++) {
      html += '<th>' + escapeHtml(columns[i]) + '</th>';
    }

    html += '</tr></thead><tbody>';

    for (var r = 0; r < rows.length; r++) {
      html += '<tr>';
      for (var c = 0; c < columns.length; c++) {
        var key = columns[c];
        var cell = rows[r][key] == null ? '' : rows[r][key];
        html += '<td>' + escapeHtml(cell) + '</td>';
      }
      html += '</tr>';
    }

    html += '</tbody></table>';
    return html;
  }

  function openPopover() {
    if (!popover) return;
    popover.classList.add('is-open');
    popover.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closePopover() {
    if (!popover) return;
    popover.classList.remove('is-open');
    popover.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
var container = document.getElementById('popoverResultsContainer');
if (container) container.scrollTop = 0;
  if (typeof TableOptions !== 'undefined') {
    if (titleEl) {
      titleEl.textContent = TableOptions.title;
    }

    if (metaEl) {
      metaEl.textContent = TableOptions.rows.length + ' row(s) returned';
    }

    if (popoverTitleEl) {
      popoverTitleEl.textContent = TableOptions.title;
    }

    if (popoverMetaEl) {
      popoverMetaEl.textContent = TableOptions.rows.length + ' row(s) returned';
    }

    if (popoverContainer) {
      popoverContainer.innerHTML = buildResultsTable(TableOptions);
    }
  }

  if (openBtn) {
    openBtn.addEventListener('click', openPopover);
  }

  if (closeTop) {
    closeTop.addEventListener('click', closePopover);
  }

  if (closeBottom) {
    closeBottom.addEventListener('click', closePopover);
  }

  if (popover) {
    popover.addEventListener('click', function (event) {
      if (event.target === popover) {
        closePopover();
      }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && popover && popover.classList.contains('is-open')) {
      closePopover();
    }
  });

  if (popover && popover.dataset.autoOpen === '1') {
    openPopover();
  }
});
</script>
<?php endif; ?>


<script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>