<?php
$_autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($_autoload)) {
    http_response_code(500);
    exit('Run composer install in the workflow_portal directory.');
}
require_once $_autoload;
(Dotenv\Dotenv::createImmutable(__DIR__ . '/..'))->safeLoad();
unset($_autoload);

define('APP_NAME',    'From Oblivion to Memory (FOM)');
define('APP_TAGLINE', 'Workflow Portal');
define('APP_BASE_URL', $_ENV['APP_BASE_URL'] ?? '');

define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'CHANGE_ME');
define('DB_USER',    $_ENV['DB_USER']    ?? 'CHANGE_ME');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME', 'fom_workflow_session');
define('CSRF_KEY',   $_ENV['CSRF_KEY']   ?? '');
define('TIMEZONE',   'America/New_York');
define('MAIL_FROM',  $_ENV['MAIL_FROM']  ?? 'noreply@obliviontomemory.org');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_MB', 20);
define('ALLOWED_EXTENSIONS', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,jpg,jpeg,png,gif,zip,csv');