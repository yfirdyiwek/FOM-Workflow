<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

if (user_role() !== 'superadmin') {
    http_response_code(403);
    render_header('Import Users', '', 'users', null);
    echo '<article class="card placeholder-card"><h2 style="margin-top:0">Access restricted</h2><p>Only the SuperAdmin can import users.</p></article>';
    render_footer();
    exit;
}

$pdo = db();
$committees = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();
$committeesByCode = [];
foreach ($committees as $c) {
    $committeesByCode[strtoupper($c['short_code'])] = (int) $c['id'];
}

$preview   = [];
$imported  = [];
$warnings  = [];
$errors    = [];
$step      = 'upload'; // upload | preview | done

// ── Handle preview (parse CSV, don't save yet) ────────────────────────────
if (is_post() && ($_POST['action'] ?? '') === 'preview_csv') {
    verify_csrf();

    if (empty($_FILES['csv_file']['tmp_name'])) {
        $errors[] = 'Please choose a CSV file to upload.';
    } else {
        $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($fh);
        if (!$headers) {
            $errors[] = 'The file appears to be empty.';
        } else {
            // Normalise header names
            $headers = array_map(fn($h) => strtolower(trim($h)), $headers);
            $required = ['first_name', 'last_name', 'email', 'username'];
            $missing  = array_diff($required, $headers);
            if ($missing) {
                $errors[] = 'Missing required columns: ' . implode(', ', $missing) . '.';
            } else {
                $rowNum = 1;
                while (($row = fgetcsv($fh)) !== false) {
                    $rowNum++;
                    if (count($row) !== count($headers)) continue; // skip malformed rows
                    $data = array_combine($headers, $row);
                    $data = array_map('trim', $data);

                    $firstName   = $data['first_name']   ?? '';
                    $lastName    = $data['last_name']    ?? '';
                    $displayName = $data['display_name'] ?? '';
                    $email       = $data['email']        ?? '';
                    $username    = $data['username']     ?? '';
                    $roleLevel   = $data['role_level']   ?? 'committee_member';
                    $committeeCodes = array_filter(array_map('trim', explode('|', $data['committee_codes'] ?? '')));

                    if ($displayName === '') $displayName = trim($firstName . ' ' . $lastName);

                    $allowedRoles = ['sc_admin','sc_member','cc_admin','fc_admin','ardc_admin','committee_member','read_only'];
                    if (!in_array($roleLevel, $allowedRoles, true)) $roleLevel = 'committee_member';

                    $rowErrors = [];
                    $rowWarnings = [];

                    if ($firstName === '') $rowErrors[] = 'first_name required';
                    if ($lastName === '')  $rowErrors[] = 'last_name required';
                    if ($email === '')     $rowErrors[] = 'email required';
                    if ($username === '')  $rowErrors[] = 'username required';

                    // Check duplicates in DB
                    if ($email !== '' || $username !== '') {
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?');
                        $stmt->execute([$email, $username]);
                        if ((int) $stmt->fetchColumn() > 0) {
                            $rowWarnings[] = 'Email or username already exists — will be skipped.';
                        }
                    }

                    // Resolve committee codes
                    $resolvedCommittees = [];
                    foreach ($committeeCodes as $code) {
                        $code = strtoupper($code);
                        if (isset($committeesByCode[$code])) {
                            $resolvedCommittees[] = $code;
                        } else {
                            $rowWarnings[] = "Unknown committee code: {$code}";
                        }
                    }

                    $preview[] = [
                        'row'         => $rowNum,
                        'first_name'  => $firstName,
                        'last_name'   => $lastName,
                        'display_name'=> $displayName,
                        'email'       => $email,
                        'username'    => $username,
                        'role_level'  => $roleLevel,
                        'committees'  => $resolvedCommittees,
                        'errors'      => $rowErrors,
                        'warnings'    => $rowWarnings,
                        'skip'        => !empty($rowErrors) || !empty($rowWarnings),
                    ];
                }
                fclose($fh);
                $step = 'preview';
            }
        }
    }
}

// ── Handle import (save confirmed rows) ───────────────────────────────────
if (is_post() && ($_POST['action'] ?? '') === 'import_users') {
    verify_csrf();
    $rows = json_decode($_POST['rows_json'] ?? '[]', true);
    $defaultPassword = password_hash('ChangeMe123!', PASSWORD_DEFAULT);

    foreach ($rows as $row) {
        if (!empty($row['errors'])) continue;
        if (!empty($row['warnings'])) continue; // skip duplicates

        $stmt = $pdo->prepare('INSERT INTO users
                (first_name, last_name, display_name, email, username, password_hash, role_level, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
        $stmt->execute([
            $row['first_name'], $row['last_name'], $row['display_name'],
            $row['email'], $row['username'], $defaultPassword, $row['role_level'],
        ]);
        $newUid = (int) $pdo->lastInsertId();

        foreach ($row['committees'] as $code) {
            $cid = $committeesByCode[strtoupper($code)] ?? null;
            if (!$cid) continue;
            $pdo->prepare('INSERT IGNORE INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin) VALUES (?, ?, "member", 0)')
                ->execute([$newUid, $cid]);
        }

        activity_log((int) current_user()['id'], 'user_created', 'user', $newUid,
            'Imported user ' . $row['display_name'] . ' via CSV.');
        $imported[] = $row['display_name'];
    }

    $step = 'done';
}

$roleLabels = [
    'sc_admin'        => 'SC Admin',
    'committee_member'=> 'Member',
    'read_only'       => 'Read Only',
];

render_header('Import Users', '', 'users', null);
?>

<style>
.import-row-ok{background:color-mix(in srgb,var(--status-completed-bg,#dcfce7) 40%,var(--surface-card))}
.import-row-warn{background:color-mix(in srgb,#fef9c3 60%,var(--surface-card))}
.import-row-err{background:color-mix(in srgb,#fee2e2 60%,var(--surface-card))}
.tag-pill{display:inline-block;font-size:.76rem;font-weight:700;padding:2px 7px;border-radius:6px;background:var(--surface-soft);margin:1px}
</style>

<article class="card section">
  <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:20px;">
    <span><a class="muted-link" href="users.php" style="font-size:.88rem;font-weight:600;">← Users</a></span>
    <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Import Users from CSV</h2>
    <span></span>
  </div>

  <?php if ($step === 'upload'): ?>

    <?php if ($errors): ?>
      <div class="alert-card returned" style="margin-bottom:16px;"><strong>Problem</strong><p><?= e(implode(' ', $errors)) ?></p></div>
    <?php endif; ?>

    <div class="panel-item" style="margin-bottom:20px;">
      <strong>CSV format</strong>
      <p>One row per user. Required columns: <code>first_name</code>, <code>last_name</code>, <code>email</code>, <code>username</code>.<br>
      Optional: <code>display_name</code>, <code>role_level</code>, <code>committee_codes</code>.</p>
      <p style="margin-top:8px;"><strong>role_level</strong> values: <code>committee_member</code> (default), <code>cc_admin</code>, <code>fc_admin</code>, <code>ardc_admin</code>, <code>sc_member</code>, <code>sc_admin</code>, <code>read_only</code>.<br>
      <strong>committee_codes</strong>: pipe-separated short codes, e.g. <code>SC|CC</code> or <code>ARDC</code>.</p>
      <p style="margin-top:8px;">Imported users get a temporary password of <code>ChangeMe123!</code> — remind them to change it.</p>
    </div>

    <div class="panel-item" style="margin-bottom:20px;">
      <strong>Example CSV</strong>
      <pre style="font-size:.82rem;margin:8px 0 0;overflow-x:auto;background:var(--surface-muted);padding:10px;border-radius:8px;">first_name,last_name,display_name,email,username,role_level,committee_codes
Elfy,Getachew,Elfy Getachew,elfy@example.com,elfy,committee_member,SC|ARDC
Wassy,Tesfa,,wassy@example.com,wassy,committee_member,CC</pre>
    </div>

    <form method="post" enctype="multipart/form-data" class="form-demo">
      <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="action"  value="preview_csv"/>
      <div class="field">
        <label for="csv_file">Choose CSV file</label>
        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required style="padding:8px;"/>
      </div>
      <div class="button-row">
        <button class="btn btn-primary" type="submit">Preview Import</button>
      </div>
    </form>

  <?php elseif ($step === 'preview'): ?>

    <?php
    $okRows   = array_filter($preview, fn($r) => empty($r['errors']) && empty($r['warnings']));
    $warnRows = array_filter($preview, fn($r) => empty($r['errors']) && !empty($r['warnings']));
    $errRows  = array_filter($preview, fn($r) => !empty($r['errors']));
    ?>

    <div class="meta-grid" style="margin-bottom:20px;">
      <div class="meta-box"><span class="k">Total rows</span><span class="v"><?= count($preview) ?></span></div>
      <div class="meta-box"><span class="k" style="color:var(--feedback-success-text,#16a34a);">Ready to import</span><span class="v"><?= count($okRows) ?></span></div>
      <div class="meta-box"><span class="k" style="color:#b45309;">Will be skipped</span><span class="v"><?= count($warnRows) + count($errRows) ?></span></div>
    </div>

    <div class="table-wrap" style="margin-bottom:20px;">
      <table>
        <thead>
          <tr>
            <th>Row</th>
            <th>Name</th>
            <th>Email / Username</th>
            <th>Role</th>
            <th>Committees</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $row): ?>
            <?php
            $rowClass = !empty($row['errors']) ? 'import-row-err' : (!empty($row['warnings']) ? 'import-row-warn' : 'import-row-ok');
            ?>
            <tr class="<?= e($rowClass) ?>">
              <td><?= (int) $row['row'] ?></td>
              <td>
                <div class="assignment-title"><?= e($row['display_name']) ?></div>
                <div class="assignment-meta"><?= e($row['first_name']) ?> <?= e($row['last_name']) ?></div>
              </td>
              <td>
                <div><?= e($row['email']) ?></div>
                <div class="assignment-meta"><?= e($row['username']) ?></div>
              </td>
              <td><span class="user-role-badge <?= e($row['role_level']) ?>"><?= e($roleLabels[$row['role_level']] ?? $row['role_level']) ?></span></td>
              <td><?php foreach ($row['committees'] as $code): ?><span class="tag-pill"><?= e($code) ?></span><?php endforeach; ?></td>
              <td>
                <?php if (!empty($row['errors'])): ?>
                  <span style="color:#b91c1c;font-size:.84rem;font-weight:700;">Error</span>
                  <div class="assignment-meta"><?= e(implode('; ', $row['errors'])) ?></div>
                <?php elseif (!empty($row['warnings'])): ?>
                  <span style="color:#b45309;font-size:.84rem;font-weight:700;">Skip</span>
                  <div class="assignment-meta"><?= e(implode('; ', $row['warnings'])) ?></div>
                <?php else: ?>
                  <span style="color:var(--feedback-success-text,#16a34a);font-size:.84rem;font-weight:700;">Ready</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (count($okRows) > 0): ?>
      <form method="post" class="button-row">
        <input type="hidden" name="_csrf"      value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"     value="import_users"/>
        <input type="hidden" name="rows_json"  value="<?= e(json_encode(array_values($preview))) ?>"/>
        <a class="btn btn-secondary" href="import-users.php">Start over</a>
        <button class="btn btn-primary" type="submit">Import <?= count($okRows) ?> user<?= count($okRows) !== 1 ? 's' : '' ?></button>
      </form>
    <?php else: ?>
      <div class="alert-card returned" style="margin-bottom:16px;"><strong>Nothing to import</strong><p>All rows have errors or duplicates. Fix the CSV and try again.</p></div>
      <a class="btn btn-secondary" href="import-users.php">Start over</a>
    <?php endif; ?>

  <?php elseif ($step === 'done'): ?>

    <div class="info-banner" style="margin-bottom:20px;">
      <strong><?= count($imported) ?> user<?= count($imported) !== 1 ? 's' : '' ?> imported</strong>
      <p>All imported accounts have a temporary password of <code>ChangeMe123!</code>.</p>
    </div>

    <?php if ($imported): ?>
      <div class="panel-list compact-panel-list" style="margin-bottom:20px;">
        <?php foreach ($imported as $name): ?>
          <div class="panel-item"><strong><?= e($name) ?></strong></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="button-row">
      <a class="btn btn-secondary" href="import-users.php">Import another file</a>
      <a class="btn btn-primary"   href="users.php">Go to Users</a>
    </div>

  <?php endif; ?>
</article>

<?php render_footer(); ?>