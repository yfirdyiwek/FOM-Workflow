<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$needsSetup = false;

try {
    $needsSetup = !has_any_users();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (current_user()) {
    redirect('dashboard.php');
}

// ── Brute-force lockout ───────────────────────────────────────────────────────
// Allow 5 failed attempts before locking out for 15 minutes.
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS    = 900; // 15 minutes

$attempts  = (int) ($_SESSION['login_attempts']  ?? 0);
$lockedAt  = (int) ($_SESSION['login_locked_at'] ?? 0);
$lockedOut = false;

if ($lockedAt > 0) {
    $elapsed = time() - $lockedAt;
    if ($elapsed < LOCKOUT_SECONDS) {
        $lockedOut = true;
        $minutes   = (int) ceil((LOCKOUT_SECONDS - $elapsed) / 60);
        $error     = "Too many failed attempts. Please wait {$minutes} minute(s) before trying again.";
    } else {
        // Lockout period has expired — reset
        $_SESSION['login_attempts']  = 0;
        $_SESSION['login_locked_at'] = 0;
        $attempts = 0;
        $lockedAt = 0;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

if (is_post() && !$needsSetup && !$error && !$lockedOut) {
    verify_csrf();
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Success — clear lockout counters and start clean session
        unset($_SESSION['login_attempts'], $_SESSION['login_locked_at']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        unset($_SESSION['_flash']);
        activity_log((int) $user['id'], 'login', 'user', (int) $user['id'], 'User logged in.');
        redirect('dashboard.php');
    }

    // Failed attempt
    $attempts++;
    $_SESSION['login_attempts'] = $attempts;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_locked_at'] = time();
        $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
    } else {
        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
        $error = "Login failed. Please check your username/email and password. ({$remaining} attempt(s) remaining)";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Login — <?= e(APP_TAGLINE) ?></title>
  <link rel="stylesheet" href="assets/styles.css"/>
</head>
<body>
<div class="app" style="grid-template-columns:1fr;min-height:100vh;">
  <main class="main" style="display:grid;place-items:center;min-height:100vh;">
    <div class="card" style="width:min(520px,92vw);padding:28px;">
      <div class="brand" style="padding:0 0 18px;border-bottom:1px solid var(--border);margin-bottom:18px;">
        <div class="brand-mark"><img src="assets/fom-icon.png" alt="<?= e(APP_NAME) ?> logo"/></div>
        <div class="brand-text"><strong><?= e(APP_NAME) ?></strong><span><?= e(APP_TAGLINE) ?></span></div>
      </div>

      <h1 style="margin:0 0 24px;">Sign in</h1>

      <?php if ($error): ?>
        <div class="alert-card returned" style="margin-bottom:16px;"><strong>Unable to continue</strong><p><?= e($error) ?></p></div>
      <?php elseif ($needsSetup): ?>
        <div class="info-banner" style="margin-bottom:16px;"><strong>First-time setup needed</strong><p>No users exist yet. <a class="muted-link" href="setup-admin.php">Create the first admin account</a>.</p></div>
      <?php endif; ?>

      <?php if (!$needsSetup && !$lockedOut): ?>
      <form method="post" class="panel-list">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
        <div class="field">
          <label for="login">Email or username</label>
          <input id="login" name="login" required autocomplete="username"/>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required autocomplete="current-password"/>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Log In</button>
        <p style="text-align:center;font-size:.9rem;margin:0;">
          <a class="muted-link" href="forgot-password.php">Forgot password?</a>
        </p>
      </form>
      <?php endif; ?>

    </div>
  </main>
</div>
<script src="assets/config.js"></script>
<script src="assets/app.js"></script>
</body>
</html>