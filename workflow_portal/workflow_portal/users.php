<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

// Only SuperAdmin can manage users
if (user_role() !== 'superadmin') {
    http_response_code(403);
    render_header('Users / Members', '', 'users', null);
    echo '<article class="card placeholder-card"><h2 style="margin-top:0">Access restricted</h2><p>Only the SuperAdmin can manage users.</p></article>';
    render_footer();
    exit;
}

$pdo = db();

// ── POST handlers ──────────────────────────────────────────────────────────
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ── Create user ────────────────────────────────────────────────────────
    if ($action === 'create_user') {
        $firstName   = trim($_POST['first_name']   ?? '');
        $lastName    = trim($_POST['last_name']    ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $email       = trim($_POST['email']        ?? '');
        $username    = trim($_POST['username']     ?? '');
        $password    = '';
        $roleLevel   = $_POST['role_level']        ?? 'committee_member';
        $committees  = (array) ($_POST['committees'] ?? []);
        $adminOf     = (array) ($_POST['admin_of']   ?? []);

        $allowedRoles = ['sc_member','sc_admin','cc_admin','fc_admin','ardc_admin','committee_member','read_only'];
        if (!in_array($roleLevel, $allowedRoles, true)) $roleLevel = 'committee_member';

        $errors = [];
        if ($firstName === '')   $errors[] = 'First name is required.';
        if ($lastName === '')    $errors[] = 'Last name is required.';
        if ($email === '')       $errors[] = 'Email is required.';
        if ($username === '')    $errors[] = 'Username is required.';
        if ($displayName === '') $displayName = trim($firstName . ' ' . $lastName);

        if (!$errors) {
            // Check uniqueness
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'That email or username is already in use.';
            }
        }

        if (!$errors) {
            // Create with a locked random password — user must set via invite link
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())')
                ->execute([$firstName, $lastName, $displayName, $email, $username, $hash, $roleLevel]);
            $newUid = (int) $pdo->lastInsertId();

            foreach ($committees as $cid) {
                $cid = (int) $cid;
                if ($cid < 1) continue;
                $isAdmin = in_array((string) $cid, array_map('strval', $adminOf), true) ? 1 : 0;
                $pdo->prepare('INSERT IGNORE INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin) VALUES (?, ?, "member", ?)')
                    ->execute([$newUid, $cid, $isAdmin]);
            }

            activity_log((int) current_user()['id'], 'user_created', 'user', $newUid,
                'Created user ' . $displayName . '.');

            // Auto-send welcome/invite email
            send_invite_email($pdo, $newUid, $email, $firstName ?: $displayName);

            flash('success', $displayName . ' was added and sent a welcome email.');
            redirect('users.php');
        }

        flash('error', implode(' ', $errors));
        redirect('users.php');
    }

    // ── Edit user ──────────────────────────────────────────────────────────
    if ($action === 'edit_user') {
        $uid         = (int) ($_POST['user_id'] ?? 0);
        $firstName   = trim($_POST['first_name']   ?? '');
        $lastName    = trim($_POST['last_name']    ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $email       = trim($_POST['email']        ?? '');
        $username    = trim($_POST['username']     ?? '');
        $password    = $_POST['password']          ?? '';
        $roleLevel   = $_POST['role_level']        ?? 'committee_member';
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $committees  = (array) ($_POST['committees'] ?? []);
        $adminOf     = (array) ($_POST['admin_of']   ?? []);

        $allowedRoles = ['sc_member','sc_admin','cc_admin','fc_admin','ardc_admin','committee_member','read_only'];
        if (!in_array($roleLevel, $allowedRoles, true)) $roleLevel = 'committee_member';

        $errors = [];
        if ($uid < 1)           $errors[] = 'Invalid user.';
        if ($firstName === '')   $errors[] = 'First name is required.';
        if ($lastName === '')    $errors[] = 'Last name is required.';
        if ($email === '')       $errors[] = 'Email is required.';
        if ($username === '')    $errors[] = 'Username is required.';
        if ($displayName === '') $displayName = trim($firstName . ' ' . $lastName);

        // Prevent editing superadmin
        $stmt = $pdo->prepare('SELECT role_level FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $target = $stmt->fetch();
        if (!$target || $target['role_level'] === 'superadmin') {
            $errors[] = 'That user cannot be edited here.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (email = ? OR username = ?) AND id != ?');
            $stmt->execute([$email, $username, $uid]);
            if ((int) $stmt->fetchColumn() > 0) $errors[] = 'That email or username is already in use.';
        }

        if (!$errors) {
            if ($password !== '') {
                if (strlen($password) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
                else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?')->execute([$hash, $uid]);
                }
            }
        }

        if (!$errors) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, display_name=?, email=?, username=?, role_level=?, is_active=?, updated_at=NOW() WHERE id=?')
                ->execute([$firstName, $lastName, $displayName, $email, $username, $roleLevel, $isActive, $uid]);

            // Rebuild memberships
            $pdo->prepare('DELETE FROM user_committee_memberships WHERE user_id = ?')->execute([$uid]);
            foreach ($committees as $cid) {
                $cid = (int) $cid;
                if ($cid < 1) continue;
                $isAdmin = in_array((string) $cid, array_map('strval', $adminOf), true) ? 1 : 0;
                $pdo->prepare('INSERT IGNORE INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin) VALUES (?, ?, "member", ?)')
                    ->execute([$uid, $cid, $isAdmin]);
            }

            activity_log((int) current_user()['id'], 'user_updated', 'user', $uid,
                'Updated user ' . $displayName . '.');
            flash('success', $displayName . ' was updated.');
            redirect('users.php');
        }

        flash('error', implode(' ', $errors));
        redirect('users.php');
    }
    // ── Send invite ────────────────────────────────────────────────────────
    if ($action === 'send_invite') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $stmt = $pdo->prepare('SELECT id, first_name, display_name, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$uid]);
            $target = $stmt->fetch();
            if ($target) {
                send_invite_email($pdo, (int) $target['id'], $target['email'], $target['first_name'] ?: $target['display_name']);
                activity_log((int) current_user()['id'], 'invite_sent', 'user', $uid, 'Sent welcome invite to ' . $target['display_name']);
                flash('success', 'Invite sent to ' . $target['display_name'] . '.');
            }
        }
        redirect('users.php');
    }
}


// ── Helper: generate reset token and send welcome email ───────────────────
function send_invite_email(PDO $pdo, int $uid, string $email, string $name): void {
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$uid]);
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$uid, $tokenHash, $expiresAt]);

    $base     = rtrim(APP_BASE_URL, '/');
    $resetUrl = ($base ? $base . '/' : '') . 'reset-password.php?token=' . urlencode($token);

    $subject = 'Welcome to ' . APP_NAME . ' — Set your password';
    $body    = "Hello {$name},

"
             . "You have been added to the FOM Workflow Portal.

"
             . "Please click the link below to set your password and activate your account.
"
             . "This link expires in 48 hours.

"
             . "{$resetUrl}

"
             . "If you were not expecting this, please contact your administrator.

"
             . "— " . APP_NAME;

    $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers   = "From: {$fromEmail}
Reply-To: {$fromEmail}
X-Mailer: PHP/" . phpversion();
    mail($email, $subject, $body, $headers);
}

// ── Data ───────────────────────────────────────────────────────────────────
$users = $pdo->query('SELECT u.*,
        GROUP_CONCAT(c.short_code ORDER BY c.short_code SEPARATOR ", ") AS committee_codes,
        GROUP_CONCAT(CASE WHEN m.is_committee_admin = 1 THEN c.short_code END ORDER BY c.short_code SEPARATOR ", ") AS admin_codes
    FROM users u
    LEFT JOIN user_committee_memberships m ON m.user_id = u.id
    LEFT JOIN committees c ON c.id = m.committee_id
    GROUP BY u.id
    ORDER BY u.role_level = "superadmin" DESC, u.display_name ASC')->fetchAll();

$committees = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();

// Build memberships map for edit pre-fill: user_id => [committee_id => is_admin]
$membershipsRaw = $pdo->query('SELECT user_id, committee_id, is_committee_admin FROM user_committee_memberships')->fetchAll();
$membershipsMap = [];
foreach ($membershipsRaw as $row) {
    $membershipsMap[(int) $row['user_id']][(int) $row['committee_id']] = (int) $row['is_committee_admin'];
}
$membershipsJson = json_encode($membershipsMap);

$roleLabels = [
    'superadmin'      => 'Super Admin',
    'sc_admin'        => 'SC Admin',
    'sc_member'       => 'SC Member',
    'cc_admin'        => 'CC Admin',
    'fc_admin'        => 'FC Admin',
    'ardc_admin'      => 'ARDC Admin',
    'committee_member'=> 'Member',
    'read_only'       => 'Read Only',
];

render_header('Users / Members', '', 'users', null);
?>

<style>
.users-header{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:16px}
.user-role-badge{display:inline-block;font-size:.76rem;font-weight:700;padding:3px 8px;border-radius:6px;background:var(--surface-muted);color:var(--text-secondary)}
.user-role-badge.superadmin{background:#1e3a5f;color:#fff}
.user-role-badge.sc_admin{background:#deebf5;color:#27445f}
.user-role-badge.sc_member{background:#e8f0fb;color:#1a3a6b}
.user-role-badge.cc_admin{background:#fef9e0;color:#7a6000}
.user-role-badge.fc_admin{background:#fde8e8;color:#7a1f1f}
.user-role-badge.ardc_admin{background:#e4f5ec;color:#1a5c38}
.user-role-badge.committee_member{background:var(--surface-soft);color:var(--text-secondary)}
.user-role-badge.read_only{background:#f3f4f6;color:#6b7280}
.inactive-row td{opacity:.5}
.committee-checks{display:grid;grid-template-columns:repeat(2,1fr);gap:8px 16px;margin-top:6px}
.committee-check-row{display:flex;align-items:center;gap:8px;font-size:.9rem}
.committee-check-row label{cursor:pointer;flex:1}
.admin-toggle{font-size:.8rem;color:var(--text-secondary);margin-left:4px}
</style>

<article class="card section">
  <div class="users-header">
    <span></span>
    <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Users / Members</h2>
    <span style="display:flex;justify-content:flex-end;gap:10px;">
      <a class="btn btn-secondary" href="import-users.php">Import CSV</a>
      <button class="btn btn-primary" type="button" data-modal-open="add-user-modal">+ Add User</button>
    </span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th data-sort="name" class="sortable">Name <span class="sort-icon"></span></th>
          <th data-sort="username" class="sortable">Username <span class="sort-icon"></span></th>
          <th data-sort="email" class="sortable">Email <span class="sort-icon"></span></th>
          <th data-sort="role" class="sortable">Role <span class="sort-icon"></span></th>
          <th data-sort="committees" class="sortable">Committees <span class="sort-icon"></span></th>
          <th data-sort="status" class="sortable">Status <span class="sort-icon"></span></th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="<?= $u['is_active'] ? '' : 'inactive-row' ?>"
              data-name="<?= e(strtolower($u['display_name'])) ?>"
              data-username="<?= e(strtolower($u['username'])) ?>"
              data-email="<?= e(strtolower($u['email'])) ?>"
              data-role="<?= e($u['role_level']) ?>"
              data-committees="<?= e(strtolower($u['committee_codes'] ?? '')) ?>"
              data-status="<?= !$u['is_active'] ? 'inactive' : (($u['role_level'] === 'superadmin' || $u['last_login_at'] !== null) ? 'active' : (str_contains($u['email'], '.invalid') ? 'seed' : 'pending')) ?>">
            <td>
              <div class="assignment-title"><?= e($u['display_name']) ?></div>
              <div class="assignment-meta"><?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?></div>
            </td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="user-role-badge <?= e($u['role_level']) ?>"><?= e($roleLabels[$u['role_level']] ?? $u['role_level']) ?></span></td>
            <td>
              <?php if ($u['committee_codes']): ?>
                <span style="font-size:.88rem;"><?= e($u['committee_codes']) ?></span>
                <?php if ($u['admin_codes']): ?>
                  <span class="assignment-meta">Admin: <?= e($u['admin_codes']) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="assignment-meta">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!$u['is_active']): ?>
                <span style="color:#9ca3af;font-weight:700;font-size:.84rem;">Inactive</span>
              <?php elseif ($u['role_level'] === 'superadmin' || $u['last_login_at'] !== null): ?>
                <span style="color:var(--feedback-success-text,#16a34a);font-weight:700;font-size:.84rem;">Active</span>
              <?php elseif (str_contains($u['email'], '.invalid')): ?>
                <span style="color:#9ca3af;font-weight:700;font-size:.84rem;" title="Placeholder email — update before sending invite">⚠ Seed user</span>
              <?php else: ?>
                <span style="color:#c8960a;font-weight:700;font-size:.84rem;">⏳ Pending</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <?php if ($u['role_level'] !== 'superadmin'): ?>
                <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;">
                  <?php if ($u['is_active'] && $u['last_login_at'] === null && !str_contains($u['email'], '.invalid')): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
                      <input type="hidden" name="action" value="send_invite"/>
                      <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>"/>
                      <button class="mini-btn" type="submit"
                              style="background:var(--committee-cc);color:#fff;border-color:var(--committee-cc);"
                              onclick="return confirm('Send welcome invite to <?= e(addslashes($u['display_name'])) ?>?')">
                        ✉ Invite
                      </button>
                    </form>
                  <?php endif; ?>
                  <button class="mini-btn" type="button"
                          data-edit-user
                          data-user-id="<?= e((string) $u['id']) ?>">Edit</button>
                </div>
              <?php else: ?>
                <span class="assignment-meta">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>

<!-- ── Add User modal ──────────────────────────────────────────────────── -->
<div class="modal-backdrop" id="add-user-modal" aria-hidden="true">
  <div class="modal-card modal-card--wide" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
    <div class="modal-card__header">
      <div>
        <h3 id="add-user-title">Add User</h3>
        <p>Create a new account. The user should change their password after first login.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body">
      <form method="post" class="form-demo" id="add-user-form">
        <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"  value="create_user"/>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="au-first">First name</label>
            <input id="au-first" name="first_name" required/>
          </div>
          <div class="field">
            <label for="au-last">Last name</label>
            <input id="au-last" name="last_name" required/>
          </div>
        </div>

        <div class="field">
          <label for="au-display">Display name <span class="admin-toggle">(leave blank to auto-fill from first + last)</span></label>
          <input id="au-display" name="display_name" placeholder="e.g. Elfy Getachew"/>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="au-email">Email</label>
            <input id="au-email" name="email" type="email" required/>
          </div>
          <div class="field">
            <label for="au-username">Username</label>
            <input id="au-username" name="username" required/>
          </div>
        </div>

        <div class="field">
          <p class="field-hint" style="margin:0 0 6px;font-size:.85rem;color:var(--text-secondary);">
            ✉ A welcome email with a password-setup link will be sent automatically.
          </p>
        </div>
        <div class="field">
            <label for="au-role">Role</label>
            <select id="au-role" name="role_level">
              <option value="committee_member">Member</option>              <option value="cc_admin">CC Admin</option>
              <option value="fc_admin">FC Admin</option>
              <option value="ardc_admin">ARDC Admin</option>
              <option value="sc_member">SC Member</option>
              <option value="sc_admin">SC Admin</option>
              <option value="read_only">Read Only</option>
            </select>
        </div>

        <div class="field">
          <label>Committee memberships</label>
          <table class="committee-table">
            <thead>
              <tr>
                <th>Committee</th>
                <th class="col-center">Member</th>
                <th class="col-center">Admin</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($committees as $c): ?>
              <tr>
                <td><span class="badge committee-badge <?= e(strtolower($c['short_code'])) ?>"><?= e($c['short_code']) ?></span> <?= e($c['name']) ?></td>
                <td class="col-center"><input type="checkbox" id="au-c-<?= e((string) $c['id']) ?>" name="committees[]" value="<?= e((string) $c['id']) ?>"/></td>
                <td class="col-center"><input type="checkbox" name="admin_of[]" value="<?= e((string) $c['id']) ?>"/></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    </div>
    <div class="modal-card__footer">
      <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit" form="add-user-form">Create User</button>
    </div>
  </div>
</div>

<!-- ── Edit User modal ─────────────────────────────────────────────────── -->
<div class="modal-backdrop" id="edit-user-modal" aria-hidden="true">
  <div class="modal-card modal-card--wide" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">
    <div class="modal-card__header">
      <div>
        <h3 id="edit-user-title">Edit User</h3>
        <p>Leave password blank to keep the current one. Unchecking Active deactivates the account without deleting history.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body">
      <form method="post" class="form-demo" id="edit-user-form">
        <input type="hidden" name="_csrf"    value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"   value="edit_user"/>
        <input type="hidden" name="user_id"  value="" id="eu-user-id"/>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="eu-first">First name</label>
            <input id="eu-first" name="first_name" required/>
          </div>
          <div class="field">
            <label for="eu-last">Last name</label>
            <input id="eu-last" name="last_name" required/>
          </div>
        </div>

        <div class="field">
          <label for="eu-display">Display name</label>
          <input id="eu-display" name="display_name"/>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="eu-email">Email</label>
            <input id="eu-email" name="email" type="email" required/>
          </div>
          <div class="field">
            <label for="eu-username">Username</label>
            <input id="eu-username" name="username" required/>
          </div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="eu-password">New password <span class="admin-toggle">(leave blank to keep current)</span></label>
            <input id="eu-password" name="password" type="password" minlength="8" placeholder="Leave blank to keep current"/>
          </div>
          <div class="field">
            <label for="eu-role">Role</label>
            <select id="eu-role" name="role_level">
              <option value="committee_member">Member</option>              <option value="cc_admin">CC Admin</option>
              <option value="fc_admin">FC Admin</option>
              <option value="ardc_admin">ARDC Admin</option>
              <option value="sc_member">SC Member</option>
              <option value="sc_admin">SC Admin</option>
              <option value="read_only">Read Only</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="eu-active" name="is_active" value="1"/>
            Active account
          </label>
        </div>

        <div class="field">
          <label>Committee memberships</label>
          <table class="committee-table" id="eu-committees">
            <thead>
              <tr>
                <th>Committee</th>
                <th class="col-center">Member</th>
                <th class="col-center">Admin</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($committees as $c): ?>
              <tr>
                <td><span class="badge committee-badge <?= e(strtolower($c['short_code'])) ?>"><?= e($c['short_code']) ?></span> <?= e($c['name']) ?></td>
                <td class="col-center"><input type="checkbox" id="eu-c-<?= e((string) $c['id']) ?>" name="committees[]" value="<?= e((string) $c['id']) ?>"/></td>
                <td class="col-center"><input type="checkbox" name="admin_of[]" value="<?= e((string) $c['id']) ?>" id="eu-a-<?= e((string) $c['id']) ?>"/></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    </div>
    <div class="modal-card__footer">
      <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit" form="edit-user-form">Save Changes</button>
    </div>
  </div>
</div>

<script>
(function(){
  // User data for edit pre-fill
  const users = <?= json_encode(array_values(array_map(fn($u) => [
      'id'           => (int) $u['id'],
      'first_name'   => $u['first_name'],
      'last_name'    => $u['last_name'],
      'display_name' => $u['display_name'],
      'email'        => $u['email'],
      'username'     => $u['username'],
      'role_level'   => $u['role_level'],
      'is_active'    => (int) $u['is_active'],
  ], array_filter($users, fn($u) => $u['role_level'] !== 'superadmin')))) ?>;

  const memberships = <?= $membershipsJson ?>;

  document.querySelectorAll('[data-edit-user]').forEach(btn => {
    btn.addEventListener('click', () => {
      const uid = parseInt(btn.dataset.userId, 10);
      const u   = users.find(x => x.id === uid);
      if (!u) return;

      document.getElementById('eu-user-id').value   = u.id;
      document.getElementById('eu-first').value      = u.first_name;
      document.getElementById('eu-last').value       = u.last_name;
      document.getElementById('eu-display').value    = u.display_name;
      document.getElementById('eu-email').value      = u.email;
      document.getElementById('eu-username').value   = u.username;
      document.getElementById('eu-role').value       = u.role_level;
      document.getElementById('eu-active').checked   = u.is_active === 1;
      document.getElementById('eu-password').value   = '';

      // Reset and set committee checkboxes
      const uMemberships = memberships[String(u.id)] || {};
      <?php foreach ($committees as $c): ?>
      (function(){
        const cid = <?= (int) $c['id'] ?>;
        const cb  = document.getElementById('eu-c-' + cid);
        const adm = document.getElementById('eu-a-' + cid);
        if (cb)  cb.checked  = String(cid) in uMemberships;
        if (adm) adm.checked = uMemberships[String(cid)] === 1;
      })();
      <?php endforeach; ?>

      const modal = document.getElementById('edit-user-modal');
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });
})();
</script>

<style>
th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
th.sortable:hover { color: var(--color-primary, #2563eb); }
th.sortable.sort-asc .sort-icon::after  { content: ' ▲'; font-size: .7em; opacity: .8; }
th.sortable.sort-desc .sort-icon::after { content: ' ▼'; font-size: .7em; opacity: .8; }
th.sortable:not(.sort-asc):not(.sort-desc) .sort-icon::after { content: ' ⇅'; font-size: .7em; opacity: .35; }
</style>

<script>
(function () {
  const table = document.querySelector('.table-wrap table');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  let currentCol = null, currentDir = 'asc';

  table.querySelectorAll('th.sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      const col = th.dataset.sort;
      if (currentCol === col) {
        currentDir = currentDir === 'asc' ? 'desc' : 'asc';
      } else {
        currentCol = col;
        currentDir = 'asc';
      }
      table.querySelectorAll('th.sortable').forEach(function (h) {
        h.classList.remove('sort-asc', 'sort-desc');
      });
      th.classList.add('sort-' + currentDir);

      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort(function (a, b) {
        const av = (a.dataset[col] || '').toLowerCase();
        const bv = (b.dataset[col] || '').toLowerCase();
        return currentDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });
  });
})();
</script>

<?php render_footer(); ?>