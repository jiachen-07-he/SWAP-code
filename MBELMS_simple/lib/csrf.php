<?php
declare(strict_types=1);

function csrf_token(): string {
    if (empty($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION[CSRF_KEY];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_validate(): void {
    if (!is_post()) return;

    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION[CSRF_KEY] ?? '');

    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}
