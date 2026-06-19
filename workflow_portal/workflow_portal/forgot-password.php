<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) { redirect('dashboard.php'); }

$pdo = db();
$sent = false;
$error = null;

if (is_post()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, display_name, email, first_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate any existing tokens for this user
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['id']]);

            // Generate secure token
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())')
                ->execute([(int) $user['id'], $tokenHash, $expiresAt]);

            // Build reset URL
            $base     = rtrim(APP_BASE_URL, '/');
            $resetUrl = ($base ? $base . '/' : '') . 'reset-password.php?token=' . urlencode($token);

            // Compose email
            $name    = $user['first_name'] ?: $user['display_name'];
            $subject = 'Password reset — ' . APP_NAME;
            $body    = "Hello {$name},\n\n"
                     . "Someone requested a password reset for your account on the FOM Workflow Portal.\n\n"
                     . "Click the link below to set a new password. This link expires in 1 hour.\n\n"
                     . "{$resetUrl}\n\n"
                     . "If you did not request this, you can safely ignore this email.\n\n"
                     . "— " . APP_NAME;

            require_once __DIR__ . '/includes/mailer.php';
            send_mail($user['email'], $subject, $body);
        }

        // Always show success to prevent email enumeration
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Forgot Password — <?= e(APP_TAGLINE) ?></title>
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

      <h1 style="margin:0 0 8px;">Forgot password</h1>

      <?php if ($sent): ?>
        <div class="info-banner" style="margin-bottom:16px;">
          <strong>Check your email</strong>
          <p>If that address is registered, a reset link has been sent. It expires in 1 hour.</p>
        </div>
        <a class="btn btn-secondary" href="login.php" style="width:100%;text-align:center;margin-top:8px;">Back to login</a>

      <?php else: ?>
        <p class="section-sub" style="margin:0 0 18px;">Enter your email address and we'll send you a link to reset your password.</p>

        <?php if ($error): ?>
          <div class="alert-card returned" style="margin-bottom:16px;"><strong>Error</strong><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <form method="post" class="panel-list">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
          <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" required autocomplete="email"/>
          </div>
          <button class="btn btn-primary" type="submit" style="width:100%;">Send Reset Link</button>
        </form>
        <p style="margin-top:16px;text-align:center;font-size:.9rem;">
          <a class="muted-link" href="login.php">Back to login</a>
        </p>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="assets/config.js"></script>
<script src="assets/app.js"></script>
</body>
</html>