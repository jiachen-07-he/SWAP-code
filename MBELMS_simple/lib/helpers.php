<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function int_param(string $key, int $default = 0): int {
    return isset($_GET[$key]) ? intval($_GET[$key]) : $default;
}

function post_int(string $key, int $default = 0): int {
    return isset($_POST[$key]) ? intval($_POST[$key]) : $default;
}

function post_str(string $key, int $maxLen = 200): string {
    $v = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    return $v;
}
