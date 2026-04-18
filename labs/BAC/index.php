<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_GET['auth'])) {
    header('Location: ?auth=false');
    exit;
}
/*
|--------------------------------------------------------------------------
| Broken Access Control + Protected Insert Workflow
|--------------------------------------------------------------------------
| Intentionally vulnerable for training use.
|--------------------------------------------------------------------------
*/

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

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* -----------------------------
   1) Access Check
------------------------------*/
$auth = isset($_GET['auth']) ? trim((string)$_GET['auth']) : 'false';
$auth_normalized = strtolower($auth);

$deny_access = (
    $auth_normalized === 'false' ||
    $auth_normalized === 'true' ||
    $auth === ''
);

/* -----------------------------
   2) Form State
------------------------------*/
$form = [
    'responder_name'   => '',
    'response_text'    => '',
    'aggression_score' => '',
    'report_date'      => '',
];

//$errors = [];
$insert_errors = [];
$reset_errors = [];
$insert_success = false;
$inserted_row = null;
$reset_success = false;
$reset_message = '';

// the helper to reset the database
function run_reset_sql(mysqli $conn, string $sqlFilePath): array
{
    if (!is_file($sqlFilePath)) {
        return [false, 'Reset file not found.'];
    }

    $sql = file_get_contents($sqlFilePath);
    if ($sql === false || trim($sql) === '') {
        return [false, 'Reset file is empty or unreadable.'];
    }

    if (!$conn->multi_query($sql)) {
        return [false, 'Reset failed: ' . $conn->error];
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        return [false, 'Reset failed: ' . $conn->error];
    }

    return [true, 'Lab data restored to default state.'];
}


if (
    !$deny_access &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'reset_lab_data'
) {
    if ($conn === null) {
        $reset_errors[] = 'DB connect failed.';
    } else {
        [$ok, $message] = run_reset_sql($conn, __DIR__ . '/resetData.sql');

        if ($ok) {
            $reset_success = true;
            $reset_message = $message;

            $form = [
                'responder_name'   => '',
                'response_text'    => '',
                'aggression_score' => '',
                'report_date'      => '',
            ];
        } else {
            $reset_errors[] = $message;
        }
    }
}

/* -----------------------------
   3) Insert Handling
------------------------------*/
if (
    !$deny_access &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'create_report'
) {
    $form['responder_name']   = trim((string)($_POST['responder_name'] ?? ''));
    $form['response_text']    = trim((string)($_POST['response_text'] ?? ''));
    $form['aggression_score'] = trim((string)($_POST['aggression_score'] ?? ''));
    $form['report_date']      = trim((string)($_POST['report_date'] ?? ''));

    if ($form['responder_name'] === '') {
        $insert_errors[] = 'Responder name is required.';
    }

    if ($form['response_text'] === '') {
        $insert_errors[] = 'Response text is required.';
    }

    if ($form['aggression_score'] === '') {
        $insert_errors[] = 'Aggression score is required.';
    } elseif (!ctype_digit($form['aggression_score'])) {
        $insert_errors[] = 'Aggression score must be a whole number.';
    } else {
        $score = (int)$form['aggression_score'];
        if ($score < 0 || $score > 3) {
            $insert_errors[] = 'Aggression score must be between 0 and 3.';
        }
    }

    if ($form['report_date'] === '') {
        $insert_errors[] = 'Report date is required.';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $form['report_date']);
        $valid_date = $dt && $dt->format('Y-m-d') === $form['report_date'];
        if (!$valid_date) {
            $insert_errors[] = 'Report date must use YYYY-MM-DD format.';
        }
    }

    if ($conn === null) {
        $insert_errors[] = 'DB connect failed.';
    }

   // if (!$errors) {
    if (!$insert_errors) {
        $sql = "
            INSERT INTO phishing_sentiment_report
                (responder_name, response_text, aggression_score, report_date)
            VALUES
                (?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt instanceof mysqli_stmt) {
            $insert_errors[] = 'Failed to prepare insert statement.';
        } else {
            $score_int = (int)$form['aggression_score'];

            $stmt->bind_param(
                'ssis',
                $form['responder_name'],
                $form['response_text'],
                $score_int,
                $form['report_date']
            );

            if ($stmt->execute()) {
                $insert_success = true;
                $inserted_row = [
                    'responder_name'   => $form['responder_name'],
                    'response_text'    => $form['response_text'],
                    'aggression_score' => (string)$score_int,
                    'report_date'      => $form['report_date'],
                ];

                $form = [
                    'responder_name'   => '',
                    'response_text'    => '',
                    'aggression_score' => '',
                    'report_date'      => '',
                ];
            } else {
                $insert_errors[] = 'Insert failed: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$test_values = [
    [
        'title' => 'Denied Value',
        'note'  => 'This reflects the unauthorized state returned by the application.',
        'text'  => '?auth=false',
    ],
    [
        'title' => 'Also Denied',
        'note'  => 'The flawed validation also blocks this explicit value.',
        'text'  => '?auth=true',
    ],
    [
        'title' => 'Fails Open',
        'note'  => 'Unexpected non-empty values bypass the deny logic and reveal the protected form.',
        'text'  => '?auth=test',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Internal Sentiment Reports</title>
  <link rel="stylesheet" href="/shared/css/theme.css?asd3ddf">
  <link rel="stylesheet" href="/shared/css/header.css">
	<script>
const resetForm = document.getElementById('resetLabDataForm');
const resetBtn = document.getElementById('resetLabDataBtn');

if (resetForm && resetBtn) {
  resetForm.addEventListener('submit', function (e) {
    const confirmed = window.confirm(
      'This will remove user-entered report data and restore the default lab values. Continue?'
    );

    if (!confirmed) {
      e.preventDefault();
      return;
    }

    resetBtn.disabled = true;
    resetBtn.textContent = 'Resetting...';
  });
}
</script>
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

<?php if ($deny_access): ?>
<div class="layout-row">
<div class="app-container panel">
  
      <div class="header-row">
        <h2>Access Restricted</h2>
      </div>
      <div class="input-panel">
     
          <h3 class="whitetext">Phishing Sentiment Report Intake</h3>
          <p>
            You do not have permission to access this application.
            If you require access, contact the application administrator.
          </p>
		  <p>Contact:  
		  <a href="mailto:admin@xz.xz">admin@xz.xz</a>
		  </p>
      </div>
    </div>


<div class="payloads-container panel">
  <div class="header-row">
    <h2>Hints</h2>
  </div>
  <div class="input-panel">
    <div class="results-launch-row" id="hintStartRow">
      <button type="button" id="showHint1">Open First Hint</button>
    </div>

    <div class="payload-block hint-card" id="hint1" hidden>
      <div class="payload-body" style="display:block;">
        <p>You were denied access, but look closely at how the application communicates that state back to the browser.</p>
        <pre>/labs/sentiment-reports.php?auth=false</pre>
        <div class="results-launch-row" style="margin-top:16px;">
          <button type="button" id="showHint2">Open Next Hint</button>
        </div>
      </div>
    </div>

    <div class="payload-block hint-card" id="hint2" hidden>
      <div class="payload-body" style="display:block;">
        <p>Try changing the value of the <code>auth</code> parameter. Test more than just obvious boolean-style values.</p>
        <pre>?auth=true
?auth=
</pre>
        <div class="results-launch-row" style="margin-top:16px;">
          <button type="button" id="showHint3">Open Final Hint</button>
        </div>
      </div>
    </div>

    <div class="payload-block hint-card" id="hint3" hidden>
      <div class="payload-body" style="display:block;">
        <p>The application denies access for a few specific values, but unexpected non-empty input falls through the validation and grants access.</p>
        <pre>?auth=false   → denied
?auth=true    → denied
?auth=        → denied
?auth=anything   → access granted</pre>
      </div>
    </div>
  </div>
</div>

    <div class="text-container">
      <div class="input-panel textborder">
        Demonstration of a broken access control vulnerability caused by inverted authorization logic, where a blacklist-style check handles a few expected values but fails open when an unexpected input is supplied.  
      </div>
    </div>
  </div>

<?php else: ?>

<div class="panel" style="display:flex;align-items:center;gap:10px;background:#f4c400;color:#111;padding:10px 14px;border-radius:8px;">
  <div style="flex:1 1 auto;">
    <div style="font-weight:700;">
      <strong>Warning:</strong> Data entered in this lab may affect usability of other labs.
      If SQLi + or other lab user-interfaces break, reset the data entered here.
    </div>

    <form method="post" action="?auth=<?= urlencode($auth) ?>" id="resetLabDataForm" style="margin-top:12px;">
<button
  type="submit"
  name="action"
  value="reset_lab_data"
  class="button primary"
  id="resetLabDataBtn"
  onclick="console.log('reset button clicked');"
>
  Reset Lab Data
</button>
    </form>
    <?php if ($reset_success): ?>
      <div class="notice success" id="resetStatusMessage" style="margin-top:10px;">
        <?= h($reset_message) ?>
      </div>
    <?php endif; ?>

    <?php if ($reset_errors): ?>
      <div class="notice error" id="resetErrorMessage" style="margin-top:10px;">
        <?php foreach ($reset_errors as $error): ?>
          <div><?= h($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div><br>
  <div class="layout-row">
  
    <div class="app-container panel">
      <div class="header-row">
        <h2>Phishing Sentiment Report Intake</h2>
      </div>

      <div class="input-panel">
        <div class="panel">
          <h3>
           Log participant responses to phishing awareness campaigns
          </h3><br>

          <form method="post" action="?auth=<?= urlencode($auth) ?>" class="form-grid">
            <input type="hidden" name="action" value="create_report" />

            <div>
              <label for="responder_name" style="color: black; font-weight: bold;">Responder Name</label>
              <input
                type="text"
                id="responder_name"
                name="responder_name"
                value="<?= h($form['responder_name']) ?>"
                maxlength="255"
                autocomplete="off"
              />
            </div>

            <div>
              <label for="response_text" style="color: black; font-weight: bold;">Response Text</label>
              <textarea
                id="response_text"
                name="response_text"
                maxlength="5000"
              ><?= h($form['response_text']) ?></textarea>
            </div>

            <div>
              <label for="aggression_score" style="color: black; font-weight: bold;">Aggression Score</label>
              <input
                type="number"
                id="aggression_score"
                name="aggression_score"
                value="<?= h($form['aggression_score']) ?>"
                min="0"
                max="3"
                step="1"
              />
            </div>

            <div>
              <label for="report_date" style="color: black; font-weight: bold;">Report Date</label>
              <input
                type="date"
                id="report_date"
                name="report_date"
                value="<?= h($form['report_date']) ?>"
              />
            </div>

            <div class="results-launch-row">
              <button type="submit">Submit Report</button>
            </div>
          </form>
		  </div></div></div>


          <?php if ($insert_errors): ?>
            <div class="message-panel input-panel ">
              <strong>Submission blocked:</strong>
              <ul>
                <?php foreach ($insert_errors as $error): ?>
                  <li><?= h($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
</div>

 

  <a class="whitetext" href="/" style="padding:20px; display:block;">Home</a>

  <?php if ($insert_success && $inserted_row !== null): ?>
    <div id="resultsPopover" class="popover-backdrop" aria-hidden="true" data-auto-open="1">
      <div class="popover-panel panel" role="dialog" aria-modal="true" aria-labelledby="popoverResultsTitle">
        <div class="input-panel">
          <div class="header-row popover-header">
            <h2 id="popoverResultsTitle" class="whitetext">Sentiment Report Logged Successfully</h2>
            <div class="popover-header-actions">
              <button type="button" id="closeResultsPopoverTop" class="popover-close-x" aria-label="Close results viewer">&times;</button>
            </div>
          </div>

          <div class="popover-body">
            <p>The following report was inserted into <code>phishing_sentiment_report</code>.</p>

            <table class="insert-preview-table">
              <tr>
                <th>Responder Name</th>
                <td><?= h($inserted_row['responder_name']) ?></td>
              </tr>
              <tr>
                <th>Response Text</th>
                <td><?= h($inserted_row['response_text']) ?></td>
              </tr>
              <tr>
                <th>Aggression Score</th>
                <td><?= h($inserted_row['aggression_score']) ?></td>
              </tr>
              <tr>
                <th>Report Date</th>
                <td><?= h($inserted_row['report_date']) ?></td>
              </tr>
            </table>

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

  <?php endif; ?>

<?php endif; ?>

</div>

</body>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var hintStartRow = document.getElementById('hintStartRow');
  var hint1 = document.getElementById('hint1');
  var hint2 = document.getElementById('hint2');
  var hint3 = document.getElementById('hint3');

  var showHint1 = document.getElementById('showHint1');
  var showHint2 = document.getElementById('showHint2');
  var showHint3 = document.getElementById('showHint3');

  if (showHint1 && hint1) {
    showHint1.addEventListener('click', function () {
      hint1.hidden = false;
      hint1.classList.add('is-visible');
      if (hintStartRow) hintStartRow.style.display = 'none';
      hint1.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (showHint2 && hint2) {
    showHint2.addEventListener('click', function () {
      hint2.hidden = false;
      hint2.classList.add('is-visible');
      showHint2.disabled = true;
      hint2.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (showHint3 && hint3) {
    showHint3.addEventListener('click', function () {
      hint3.hidden = false;
      hint3.classList.add('is-visible');
      showHint3.disabled = true;
      hint3.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
});
</script>
</html>