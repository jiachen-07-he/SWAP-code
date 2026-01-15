<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/db.php';

// Session (basic secure defaults)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Singapore');

// Shared libs
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/flash.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/audit.php';
