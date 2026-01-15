<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (is_post()) {
    csrf_validate();
    audit_log('logout', 'users', current_user()['id'] ?? null);
    auth_logout();
}

flash_set('success', 'Logged out.');
redirect('/pages/login.php');
