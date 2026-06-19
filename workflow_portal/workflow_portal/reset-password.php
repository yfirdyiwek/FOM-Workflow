<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) { redirect('dashboard.php'); }

$pdo   = db();
$token = trim($_GET['token'] ?? '');
$error = null;
$done  = false;
$validToken = null;

if ($token === '') {
    $error = 'Invalid or missing reset link.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT pr.*, u.display_name, u.first_name FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
        LIMIT 1');
    $stmt->execute([$tokenHash]);
    $validToken = $stmt->fetch();
    if (!$validToken) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if (is_post() && $validToken && !$error) {
    verify_csrf();
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?')
            ->execute([$hash, (int) $validToken['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')
            ->execute([(int) $validToken['id']]);
        activity_log((int) $validToken['user_id'], 'password_reset', 'user', (int) $validToken['user_id'], 'Password reset via email link.');
        $done = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Reset Password — <?= e(APP_TAGLINE) ?></title>
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

      <h1 style="margin:0 0 8px;">Set new password</h1>

      <?php if ($done): ?>
        <div class="info-banner" style="margin-bottom:16px;">
          <strong>Password updated</strong>
          <p>Your password has been changed. You can now log in.</p>
        </div>
        <a class="btn btn-primary" href="login.php" style="width:100%;text-align:center;">Go to login</a>

      <?php elseif ($error): ?>
        <div class="alert-card returned" style="margin-bottom:16px;"><strong>Problem</strong><p><?= e($error) ?></p></div>
        <a class="muted-link" href="forgot-password.php">Request a new reset link</a>

      <?php else: ?>
        <p class="section-sub" style="margin:0 0 18px;">
          Hello <?= e($validToken['first_name'] ?: $validToken['display_name']) ?>, enter your new password below.
        </p>

        <form method="post" class="panel-list">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
          <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password" placeholder="Min 8 characters"/>
          </div>
          <div class="field">
            <label for="password2">Confirm new password</label>
            <input id="password2" name="password2" type="password" minlength="8" required autocomplete="new-password"/>
          </div>
          <button class="btn btn-primary" type="submit" style="width:100%;">Set Password</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="assets/config.js"></script>
<script src="assets/app.js"></script>
</body>
</html>