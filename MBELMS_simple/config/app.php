<?php
// =========================
// Application configuration
// =========================

declare(strict_types=1);

define('APP_NAME', 'MBELMS');

// Base URL (works when project is inside a folder like /mbelms)
define(constant_name: 'BASE_URL', value: '/MBELMS_simple');


// Database configuration (update if needed)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mbelms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security
define('SESSION_NAME', 'mbelms_session');
define('CSRF_KEY', 'mbelms_csrf_token');
