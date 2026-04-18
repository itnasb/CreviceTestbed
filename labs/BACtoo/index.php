<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
/*
|--------------------------------------------------------------------------
Broken access control + stored XSS lab
|--------------------------------------------------------------------------
*/

/* -----------------------------
   1) Config & DB Connection
------------------------------*/
//$db_host = 'db';
$db_host = '127.0.0.1';
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
   2) Helpers / Banner Storage
------------------------------*/
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function banner_file_path(): string
{
    return __DIR__ . '/data/banner-message';
}

function default_banner_html(): string
{
    return <<<HTML
<div style="display:flex;align-items:center;gap:10px;background:#f4c400;color:#111;padding:10px 14px;border-radius:8px;">
  <img src="/shared/images/maintain.png" alt="Scheduled maintenance" style="width:40px;height:40px;object-fit:contain;display:block;flex:0 0 auto;">
  <span style="font-weight:700;">This page will be down for maintenance next week</span>
</div>
HTML;
}

function ensure_banner_file(): bool
{
    $path = banner_file_path();
    $dir = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (!file_exists($path)) {
        return @file_put_contents($path, default_banner_html(), LOCK_EX) !== false;
    }

    $existing = @file_get_contents($path);
    if ($existing === false || trim($existing) === '') {
        return @file_put_contents($path, default_banner_html(), LOCK_EX) !== false;
    }

    return true;
}

function read_banner_html(): string
{
    $path = banner_file_path();

    if (!file_exists($path)) {
        ensure_banner_file();
    }

    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return default_banner_html();
    }

    return (string)$content;
}

function write_banner_html(string $content): bool
{
    $path = banner_file_path();
    ensure_banner_file();
    return @file_put_contents($path, $content, LOCK_EX) !== false;
}

function restore_banner_html(): bool
{
    return write_banner_html(default_banner_html());
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------
   3) Lightweight API Actions
------------------------------*/
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

if ($action === 'permissions') {
    json_response([
        'roles' => ['user'],
        'permissions' => [
            'view' => true,
            'admin' => false,
        ],
    ]);
}

if ($action === 'get_banner') {
    json_response([
        'success' => true,
        'banner' => read_banner_html(),
    ]);
}

if ($action === 'save_banner') {
    $banner_html = isset($_POST['banner_html']) ? (string)$_POST['banner_html'] : '';

    if (!write_banner_html($banner_html)) {
        json_response([
            'success' => false,
            'message' => 'Failed to update banner message.',
        ], 500);
    }

    json_response([
        'success' => true,
        'message' => 'Banner updated successfully. Check the main page.',
    ]);
}

if ($action === 'restore_banner') {
    if (!restore_banner_html()) {
        json_response([
            'success' => false,
            'message' => 'Failed to restore banner message.',
        ], 500);
    }

    json_response([
        'success' => true,
        'message' => 'Banner restored successfully.',
        'banner' => default_banner_html(),
    ]);
}

/* -----------------------------
   4) Page State
------------------------------*/
$search_term = '';
$search_executed = false;
$rows = [];

/* -----------------------------
   5) Search Handling
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

    $wildcardSearch = "%" . $search_term . "%";

    $sql = "SELECT responder_name, response_text, aggression_score, report_date
            FROM phishing_sentiment_report
            WHERE responder_name LIKE ?
            ORDER BY responder_name, report_date DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $conn->error;
        exit;
    }

    $stmt->bind_param("s", $wildcardSearch);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res instanceof mysqli_result) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
        $stmt->close();
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $conn->error;
        $stmt->close();
        exit;
    }
}

/* -----------------------------
   6) Lab Instruction Content
------------------------------*/
$payload_templates = [
    [
        'title' => 'Step 1 - Inspect Network Traffic',
        'note'  => "Open the browser DevTools and review the Network panel as you reload the page\n\nLook for where the page requests permission data and where those values are checked.",
        'text'  => "labs/BACToo?action=permissions",
    ],
        [
        'title' => 'Step 1.2 - View the Response',
        'note'  => "click the request and to the right you should see a window that allows you to view the response",
        'text'  => "{\"roles\":[\"user\"],\"permissions\":{\"view\":true,\"admin\":false}}",
    ],
        [
        'title' => 'Inspect custom.js for permissions related keywords',
        'note'  => "\nYou should see keywords like \"permissions\" and \"admin\".\n\nExplore the related functionality.",
        'text'  => "",
        ],
    [
        'title' => 'Step 2.1 - Set two breakpoints',
        'note'  => "\nPlace one breakpoint where the page decides whether to render the wrench.\n\nPlace a second breakpoint where clicking the wrench checks whether the banner tools panel should be opened.\n\nYou will find the positions that need breakpoints have comments to guide you.",
        'text'  => "\"if\" in renderWrench()\n\"if (!isAdmin)\" in openBannerPanel()",
    ],
    [
        'title' => 'Step 3 - Reload and tamper',
        'note'  => "Reload the page after setting the breakpoints.\n\nWhen paused, change the accessState.permissions.admin value from false to true and continue execution.\n\nThis can be done consistently via the console with the following JavaScript:",
        'text'  => "accessState.permissions.admin = true;",
    ],
    [
        'title' => 'Step 4 - Open the tools panel',
        'note'  => "Click the Admin wrench icon\n\nWhen the second breakpoint pauses, change the data.permissions.admin value from false to true and contiue execution.\n\nThis can be done consistently via the console with the following JavaScript:",
        'text'  => "isAdmin = true;",
    ],
    [
        'title' => 'Step 5 - Try a script tag first',
        'note'  => "The page loads the banner as data and writes it into the page using innerHTML.\n\nNotice that injecting a script tag is not a reliable way to execute code in this sink.",
        'text'  => '<script>alert(1)</script>',
    ],
    [
        'title' => 'Step 6 - Use an image/event payload',
        'note'  => "Since the banner accepts raw HTML and images are expected here, try an image-based payload instead.",
        'text'  => '<img src="x" onerror="alert(1)">',
    ],
    [
        'title' => 'Optional - Proxy tampering path',
        'note'  => "\nIn day to day work, once you found this vulnerability, you would use something like Burp Suite and match-and-replace rules to automatically modify false to true in responses rather than editing state in the debugger.",
        'text'  => '',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Broken Access Control + Stored XSS</title>
  <link rel="stylesheet" href="/shared/css/theme.css">
  <link rel="stylesheet" href="/shared/css/header.css">
  <link rel="stylesheet" href="/shared/css/banner.css">

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

  <div class="banner-utility-row">
    <button type="button" id="restoreBannerBtn">Restore Default Banner</button>
  </div>

  <div id="bannerMessageWrap" class="banner-message-wrap" style="display:none;">
    <div id="bannerMessage"></div>
  </div>

  <div class="tool-row">
    <div id="wrenchMount"></div>
  </div>

  <div class="layout-row">
    <div class="app-container panel">
      <div class="header-row"><h2>Phishing Campaign Sentiment Log</h2></div>

      <div class="input-panel">
        <div class="panel">
          <h3>View User Sentiment Reports</h3>

          <form id="searchForm" method="get" action="">
            <input type="hidden" name="action" value="search" />
            <label for="search_term">Search term</label>

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
        </div>
      </div>
    </div>

    <div class="payloads-container panel">
      <div class="header-row">
        <h2>Lab Guide</h2>
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
      </div>
    </div>

    <div class="text-container">
      <div class="input-panel textborder lab-note">
        Demonstration of a broken access control issue in a reporting application that trusts client-side permission checks before exposing privileged functionality.

        The permissions response is checked in two different JavaScript checks: first to decide whether the wrench should be shown, and later to decide whether the banner tools panel can be opened.

        The banner editor stores raw HTML in a shared page banner. Because the save action performs no server-side authorization and the banner is rendered with innerHTML on the main page, unauthorized access to the tools feature can lead to stored XSS.
      </div>
    </div>
  </div>

  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>
</div>

<div id="toolsPopover" class="popover-backdrop" aria-hidden="true">
  <div class="popover-panel panel" role="dialog" aria-modal="true" aria-labelledby="toolsPopoverTitle">
    <div class="input-panel tool-editor">
      <div class="popover-header">
        <h2 id="toolsPopoverTitle" class="whitetext">Banner Tools</h2>
        <div class="popover-header-actions">
          <button type="button" id="closeToolsPopoverTop" class="popover-close-x" aria-label="Close banner editor">&times;</button>
        </div>
      </div>

      <div class="popover-body">
        <label for="bannerEditor" class="whitetext">Banner message</label>
        <textarea id="bannerEditor" spellcheck="false"></textarea>

        <div class="tool-actions">
          <button type="button" id="saveBannerBtn">Save Banner</button>
          <button type="button" id="restoreBannerBtnTools">Restore Default Banner</button>
          <button type="button" id="closeToolsPopoverBottom">Close</button>
        </div>

        <div id="statusMessage" class="status-message"></div>
      </div>
    </div>
  </div>
</div>

<?php if ($search_executed && !empty($rows)): ?>
<div id="resultsPopover" class="popover-backdrop" aria-hidden="true" data-auto-open="1">
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
</div>
<?php endif; ?>

<?php if ($search_executed && !empty($rows)): ?>
<script>
var TableOptions = {
  columns: ['responder_name','response_text','aggression_score','report_date'],
  rows: <?= json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  searchTerm: '<?= $search_term ?>',
  title: 'Search Results for: ' + '<?= h($search_term) ?>',
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
    if (titleEl) titleEl.textContent = TableOptions.title;
    if (metaEl) metaEl.textContent = TableOptions.rows.length + ' row(s) returned';
    if (popoverTitleEl) popoverTitleEl.textContent = TableOptions.title;
    if (popoverMetaEl) popoverMetaEl.textContent = TableOptions.rows.length + ' row(s) returned';
    if (popoverContainer) popoverContainer.innerHTML = buildResultsTable(TableOptions);
  }

  if (openBtn) openBtn.addEventListener('click', openPopover);
  if (closeTop) closeTop.addEventListener('click', closePopover);
  if (closeBottom) closeBottom.addEventListener('click', closePopover);

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
<script src="/shared/scripts/custom.js"></script>

</body>
</html>
