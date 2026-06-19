<?php
require_once __DIR__ . '/includes/bootstrap.php';

try {
    if (!has_any_users()) {
        redirect('setup-admin.php');
    }
} catch (Throwable) {
    // fall through to login and let the friendly error page explain next steps
}

if (current_user()) {
    redirect('dashboard.php');
}

redirect('login.php');
