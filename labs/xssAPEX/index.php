<?php
$report = $_GET['report'] ?? '';
$report_id = $_GET['report_id'] ?? '';
$format = $_GET['format'] ?? '';
$download = $_GET['download'] ?? '';

$report_options = [
    '1001' => 'Nicest User',
    '1002' => 'Most Encouraging',
    '1003' => 'Passive Aggressive',
    '1004' => 'Overtly Aggressive',
    '1005' => 'Full Sentiment Log',
];

$format_options = ['PDF', 'CSV', 'HTML'];

$payload_templates = [
    [
        'title' => 'Request a normal report first',
        'note'  => "\n\nSelect a report and output format, then click Fetch Report.\n\nObserve that the application redirects to a GET request containing a report token in the URL.",
        'text'  => '?report=1001:PDF',
    ],
    [
        'title' => 'Try adding unexpected text after the format',
        'note'  => "\n\nModify the GET parameter directly in the address bar.\n\nObserve the verbose error response and how your malformed token is reflected back.",
        'text'  => '?report=1001:PDFTEST',
    ],
    [
        'title' => 'Try a basic XSS payload',
        'note'  => "\n\nObserve that your payload appears to be handled safely in the error message.",
        'text'  => '?report=1001:PDF<script>alert(1)</script>',
    ],
    [
        'title' => 'Try changing the behavior with a percent sign',
        'note'  => "\n\nObserver that the application encodes your input until the percent sign, then stops encoding the rest.",
        'text'  => '?report=1001:PDF<script>alert(1)</script>%<script>alert(2)</script>',
    ],
];

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
 * Intentionally flawed output encoding for training purposes.
 * Encodes characters until a percent sign is encountered, then appends
 * the rest of the string raw into the HTML error message.
 */
function flawed_error_encode(string $input): string {
    $out = '';
    $raw_mode = false;
    $len = strlen($input);

    for ($i = 0; $i < $len; $i++) {
        $ch = $input[$i];

        if ($ch === '%') {
            $raw_mode = true;
            $out .= '%';
            continue;
        }

        if ($raw_mode) {
            $out .= $ch;
        } else {
            $out .= htmlspecialchars($ch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    return $out;
}

function parse_report_token(string $token): array {
    $token = trim($token);

    if ($token === '') {
        return [
            'ok'      => false,
            'reason'  => 'empty',
            'message' => 'No report token was provided.',
        ];
    }

    if (!str_contains($token, ':')) {
        return [
            'ok'      => false,
            'reason'  => 'structure',
            'message' => 'Malformed report token. Expected format REPORT_ID:FILE_TYPE.',
        ];
    }

    [$reportId, $format] = explode(':', $token, 2);

    if ($reportId === '' || $format === '') {
        return [
            'ok'      => false,
            'reason'  => 'structure',
            'message' => 'Malformed report token. Expected format REPORT_ID:FILE_TYPE.',
        ];
    }

    if (!preg_match('/^\d+$/', $reportId)) {
        return [
            'ok'      => false,
            'reason'  => 'report_id',
            'message' => 'Invalid report identifier.',
        ];
    }

    $formatUpper = strtoupper($format);

    if (!preg_match('/^[A-Z]+$/', $formatUpper)) {
        return [
            'ok'      => false,
            'reason'  => 'format_chars',
            'message' => 'Output type contains unexpected characters.',
        ];
    }

    $allowed = ['PDF', 'CSV', 'HTML'];

    if (!in_array($formatUpper, $allowed, true)) {
        return [
            'ok'      => false,
            'reason'  => 'format_value',
            'message' => 'Unsupported output type.',
        ];
    }

    return [
        'ok'       => true,
        'reportId' => $reportId,
        'format'   => $formatUpper,
    ];
}

/*
 * Friendly UI request:
 * ?report_id=1001&format=PDF
 *
 * Canonical backend request:
 * ?report=1001:PDF
 *
 * Redirect so students can see and tamper with the real parameter in the URL.
 */
if ($report === '' && $report_id !== '' && $format !== '') {
    $canonical = $report_id . ':' . strtoupper($format);
    header('Location: ?report=' . rawurlencode($canonical));
    exit;
}

$reportFiles = [
    '1001' => [
        'PDF'  => __DIR__ . '/reports/nicest-user.pdf',
        'CSV'  => __DIR__ . '/reports/nicest-user.csv',
        'HTML' => __DIR__ . '/reports/nicest-user.html',
    ],
    '1002' => [
        'PDF'  => __DIR__ . '/reports/most-encouraging.pdf',
        'CSV'  => __DIR__ . '/reports/most-encouraging.csv',
        'HTML' => __DIR__ . '/reports/most-encouraging.html',
    ],
    '1003' => [
        'PDF'  => __DIR__ . '/reports/passive-aggressive.pdf',
        'CSV'  => __DIR__ . '/reports/passive-aggressive.csv',
        'HTML' => __DIR__ . '/reports/passive-aggressive.html',
    ],
    '1004' => [
        'PDF'  => __DIR__ . '/reports/overtly-aggressive.pdf',
        'CSV'  => __DIR__ . '/reports/overtly-aggressive.csv',
        'HTML' => __DIR__ . '/reports/overtly-aggressive.html',
    ],
    '1005' => [
        'PDF'  => __DIR__ . '/reports/full-sentiment-log.pdf',
        'CSV'  => __DIR__ . '/reports/full-sentiment-log.csv',
        'HTML' => __DIR__ . '/reports/full-sentiment-log.html',
    ],
];

$contentTypes = [
    'PDF'  => 'application/pdf',
    'CSV'  => 'text/csv; charset=UTF-8',
    'HTML' => 'text/html; charset=UTF-8',
];

$downloadNames = [
    '1001' => [
        'PDF'  => 'nicest-user.pdf',
        'CSV'  => 'nicest-user.csv',
        'HTML' => 'nicest-user.html',
    ],
    '1002' => [
        'PDF'  => 'most-encouraging.pdf',
        'CSV'  => 'most-encouraging.csv',
        'HTML' => 'most-encouraging.html',
    ],
    '1003' => [
        'PDF'  => 'passive-aggressive.pdf',
        'CSV'  => 'passive-aggressive.csv',
        'HTML' => 'passive-aggressive.html',
    ],
    '1004' => [
        'PDF'  => 'overtly-aggressive.pdf',
        'CSV'  => 'overtly-aggressive.csv',
        'HTML' => 'overtly-aggressive.html',
    ],
    '1005' => [
        'PDF'  => 'full-sentiment-log.pdf',
        'CSV'  => 'full-sentiment-log.csv',
        'HTML' => 'full-sentiment-log.html',
    ],
];

$should_auto_open = false;
$popover_title = 'System Response';
$popover_meta = 'Awaiting input...';
$popover_body_html = '<div class="results-empty">Select a report and output type to retrieve a phishing sentiment report.</div>';

$headerBlockText = '';
$scriptBlockText = '';

$selected_report_id = '1001';
$selected_format = 'PDF';

$download_ready = false;
$download_label = '';
$download_href = '';

if ($report !== '') {
    $result = parse_report_token($report);

    if ($result['ok'] === true) {
        $selected_report_id = $result['reportId'];
        $selected_format = $result['format'];

        $file = $reportFiles[$selected_report_id][$selected_format] ?? '';

        if (!isset($report_options[$selected_report_id])) {
            $should_auto_open = true;
            $popover_title = 'Malformed Request';
            $popover_meta = 'Unknown report identifier';
            $popover_body_html =
                '<div class="results-message">' .
                    '<p><strong>Unknown report identifier.</strong></p>' .
                    '<p>Invalid report token: <span class="raw-uri">' . flawed_error_encode($report) . '</span></p>' .
                    '<p>Expected token format: <code>REPORT_ID:FILE_TYPE</code></p>' .
                '</div>';
        } elseif ($file !== '' && is_file($file)) {
            if ($download === '1') {
                header('X-Content-Type-Options: nosniff');
                header('Content-Type: ' . $contentTypes[$selected_format]);
                header('Content-Disposition: attachment; filename="' . $downloadNames[$selected_report_id][$selected_format] . '"');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }

            $download_ready = true;
            $download_label = $report_options[$selected_report_id] . ' (' . $selected_format . ')';
            $download_href = '?report=' . rawurlencode($report) . '&download=1';
        } else {
            $should_auto_open = true;
            $popover_title = 'Report Retrieval Error';
            $popover_meta = 'Static report asset missing';
            $popover_body_html =
                '<div class="results-message">' .
                    '<p><strong>Report file missing from server.</strong></p>' .
                    '<p>The requested output type was accepted, but the static report file could not be located.</p>' .
                '</div>';
        }
    } else {
        $should_auto_open = true;
        $popover_title = 'Malformed Request';
        $popover_meta = '';

        $popover_body_html =
            '<div class="results-message">' .
                '<p><strong>' . h($result['message']) . '</strong></p>' .
                '<p>Invalid report token: <span class="raw-uri">' . flawed_error_encode($report) . '</span></p>' .
                '<p>Expected token format: <code>REPORT_ID:FILE_TYPE</code></p>' .
                '<p>Supported output types: <code>PDF</code>, <code>CSV</code>, <code>HTML</code></p>' .
            '</div>';


    }
} elseif ($report_id !== '' && isset($report_options[$report_id])) {
    $selected_report_id = $report_id;
}

if ($format !== '' && in_array(strtoupper($format), $format_options, true)) {
    $selected_format = strtoupper($format);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Report Retrieval Error XSS</title>
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
        <h2>Report Retrieval Error XSS</h2>
      </div>

      <div class="input-panel">
        <div class="panel">
          <h3>Retrieve Sentiment Report</h3>

          <form method="GET">
            <label for="report_id">Report</label>
            <select name="report_id" id="report_id">
              <?php foreach ($report_options as $id => $label): ?>
                <option value="<?php echo h($id); ?>" <?php echo $selected_report_id === $id ? 'selected' : ''; ?>>
                  <?php echo h($label); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="format" style="margin-top:10px;">Output Type</label>
            <select name="format" id="format">
              <?php foreach ($format_options as $fmt): ?>
                <option value="<?php echo h($fmt); ?>" <?php echo $selected_format === $fmt ? 'selected' : ''; ?>>
                  <?php echo h($fmt); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <button type="submit" style="margin-top:10px;">Fetch Report</button>
          </form>

          <?php if ($download_ready): ?>
            <div class="input-panel textborder" style="margin-top:12px; width:97%;">
              <strong>Report ready:</strong> <?php echo h($download_label); ?><br><br>
              <a href="<?php echo h($download_href); ?>" style="text-decoration:none;">
                <button type="button">Download Report</button>
              </a>
            </div>
          <?php endif; ?>

          
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
            <div class="payload-body" id="payload-<?php echo $idx; ?>" style="display:none;">
              <p><strong><?php echo h($p['title']); ?>:</strong> <?php echo nl2br(h($p['note'])); ?></p>
              <pre><?php echo h($p['text']); ?></pre>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="text-container">
      <div class="input-panel textborder">
        Demonstration of reflected XSS in a verbose server-side error message caused by inconsistent HTML encoding. Malformed values are reflected into a verbose HTML error response. Input appears safely encoded until the application encounters a percent sign, after which the remaining user-controlled content is inserted without HTML encoding.
      </div>
    </div>
  </div>

  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>
</div>

<div id="resultsPopover"
     class="popover-backdrop"
     aria-hidden="true"
     data-auto-open="<?php echo $should_auto_open ? '1' : '0'; ?>">
  <div class="popover-panel panel" role="dialog" aria-modal="true" aria-labelledby="popoverResultsTitle">
    <div class="input-panel">
      <div class="popover-header">
        <h2 id="popoverResultsTitle" class="whitetext"><?php echo h($popover_title); ?></h2>
        <div class="popover-header-actions">
          <button type="button" id="closeResultsPopoverTop" class="popover-close-x" aria-label="Close results viewer">&times;</button>
        </div>
      </div>

      <div class="popover-body">
        <div id="popoverResultsMeta" class="whitetext popover-meta"><?php echo h($popover_meta); ?></div>

        <div id="popoverResultsContainer" class="table-wrap results-scroll">
          <?php echo $popover_body_html; ?>
        </div>

        <div class="results-launch-row" style="margin-top:16px;">
          <button type="button" id="closeResultsPopoverBottom">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var popover = document.getElementById('resultsPopover');
  var closeTop = document.getElementById('closeResultsPopoverTop');
  var closeBottom = document.getElementById('closeResultsPopoverBottom');

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