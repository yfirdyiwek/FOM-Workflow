<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$created = false;

try {
    if (has_any_users()) {
        if (is_logged_in()) {
            redirect('dashboard.php');
        }
        flash('error', 'An admin user already exists. Please log in instead.');
        redirect('login.php');
    }

    $pdo = db();

    if (is_post()) {
        verify_csrf();
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (!$firstName || !$lastName || !$email || !$username || !$password) {
            throw new RuntimeException('Please complete all required fields.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        if (strlen($password) < 10) {
            throw new RuntimeException('Use a password at least 10 characters long.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
        $stmt->execute([
            $firstName,
            $lastName,
            $displayName ?: trim($firstName . ' ' . $lastName),
            $email,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            'superadmin',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $committeeIds = $pdo->query('SELECT id FROM committees')->fetchAll(PDO::FETCH_COLUMN);
        $insertMembership = $pdo->prepare('INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin, created_at) VALUES (?, ?, ?, 1, NOW())');
        foreach ($committeeIds as $committeeId) {
            $insertMembership->execute([$userId, (int) $committeeId, 'member']);
        }

        $pdo->commit();
        activity_log($userId, 'setup_admin', 'user', $userId, 'Initial superadmin account created.');
        flash('success', 'Admin account created. Please log in.');
        redirect('login.php');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Setup Admin — <?= e(APP_TAGLINE) ?></title>
  <link rel="stylesheet" href="assets/styles.css"/>
</head>
<body>
<div class="app" style="grid-template-columns:1fr;min-height:100vh;">
  <main class="main" style="display:grid;place-items:center;min-height:100vh;">
    <div class="card" style="width:min(720px,94vw);padding:28px;">
      <div class="brand" style="padding:0 0 18px;border-bottom:1px solid var(--border);margin-bottom:18px;">
        <div class="brand-mark"><img src="assets/fom-icon.png" alt="<?= e(APP_NAME) ?> logo"/></div>
        <div class="brand-text"><strong><?= e(APP_NAME) ?></strong><span><?= e(APP_TAGLINE) ?> — first-time setup</span></div>
      </div>

      <h1 style="margin:0 0 8px;">Create the first admin account</h1>
      <p class="section-sub" style="margin:0 0 18px;">This page only works when the database has no users yet. The first account becomes the SuperAdmin and is added to all committees.</p>

      <?php if ($error): ?>
        <div class="alert-card returned" style="margin-bottom:16px;"><strong>Setup error</strong><p><?= e($error) ?></p></div>
      <?php endif; ?>

      <form method="post" class="panel-list">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
        <div class="layout-two-equal" style="gap:16px;">
          <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" required/></div>
          <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" required/></div>
        </div>
        <div class="layout-two-equal" style="gap:16px;">
          <div class="field"><label for="display_name">Display name</label><input id="display_name" name="display_name" placeholder="Optional"/></div>
          <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required/></div>
        </div>
        <div class="layout-two-equal" style="gap:16px;">
          <div class="field"><label for="username">Username</label><input id="username" name="username" required/></div>
          <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required/></div>
        </div>
        <div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" required/></div>
        <div class="button-row">
          <button class="btn btn-primary" type="submit">Create Admin Account</button>
          <a class="btn btn-secondary" href="login.php">Back to Login</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="assets/config.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
