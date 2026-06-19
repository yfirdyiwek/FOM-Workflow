<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    activity_log((int) $user['id'], 'logout', 'user', (int) $user['id'], 'User logged out.');
}
logout_user();
flash('success', 'You have been logged out.');
redirect('login.php');
