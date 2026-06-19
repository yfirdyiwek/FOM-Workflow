<?php
require_once __DIR__ . '/config.php';

// Harden session cookie before session starts:
// - secure:   only send cookie over HTTPS
// - httponly: block JavaScript from reading the cookie
// - samesite: prevent cookie from being sent on cross-site requests
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

require_once __DIR__ . '/auth.php';