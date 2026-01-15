<?php
declare(strict_types=1);

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}



function bruteforce_limits(): array
{
    return [
        'max_attempts' => 5,
        'window_seconds' => 15 * 60,
        'lock_seconds' => 15 * 60,
        'fail_delay_ms' => 300,
    ];
}

function bruteforce_is_locked(mysqli $conn, string $username, string $ip): bool
{
    $sql = 'SELECT locked_until FROM login_attempts WHERE username = ? AND ip = INET6_ATON(?) LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $ip);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || empty($row['locked_until'])) {
        return false;
    }

    return strtotime((string) $row['locked_until']) > time();
}

function bruteforce_register_failure(mysqli $conn, string $username, string $ip): void
{
    $cfg = bruteforce_limits();
    $now = date('Y-m-d H:i:s');
    $windowStart = time() - $cfg['window_seconds'];

    $conn->begin_transaction();
    try {
        $sql = 'SELECT attempts, first_attempt_at FROM login_attempts WHERE username = ? AND ip = INET6_ATON(?) FOR UPDATE';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $username, $ip);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $sql = 'INSERT INTO login_attempts
            (username, ip, first_attempt_at, last_attempt_at, attempts, locked_until)
        VALUES (?, INET6_ATON(?), ?, ?, 1, NULL)';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $username, $ip, $now, $now);

            $stmt->execute();
            $stmt->close();
            $conn->commit();
            return;
        }

        $firstAt = strtotime((string) $row['first_attempt_at']);
        $attempts = (int) $row['attempts'];

        if ($firstAt < $windowStart) {
            $attempts = 0;
            $firstAt = time();
        }

        $attempts++;

        $lockedUntil = null;
        if ($attempts >= $cfg['max_attempts']) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $cfg['lock_seconds']);
        }

        $firstStr = date('Y-m-d H:i:s', $firstAt);

        $sql = 'UPDATE login_attempts
                SET attempts = ?, first_attempt_at = ?, last_attempt_at = ?, locked_until = ?, updated_at = ?
                WHERE username = ? AND ip = INET6_ATON(?)';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issssss', $attempts, $firstStr, $now, $lockedUntil, $now, $username, $ip);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}


function bruteforce_status(mysqli $conn, string $username, string $ip): array
{
    $sql = 'SELECT attempts, locked_until, first_attempt_at, last_attempt_at
            FROM login_attempts
            WHERE username = ? AND ip = INET6_ATON(?)
            LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $ip);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return [
            'attempts' => 0,
            'locked_until' => null,
            'first_attempt_at' => null,
            'last_attempt_at' => null,
        ];
    }

    return [
        'attempts' => (int) $row['attempts'],
        'locked_until' => $row['locked_until'], // string or null
        'first_attempt_at' => $row['first_attempt_at'],
        'last_attempt_at' => $row['last_attempt_at'],
    ];
}

function bruteforce_attempts_left(int $attempts): int
{
    $max = (int) bruteforce_limits()['max_attempts'];
    return max(0, $max - $attempts);
}

function bruteforce_clear(mysqli $conn, string $username, string $ip): void
{
    $sql = 'DELETE FROM login_attempts WHERE username = ? AND ip = INET6_ATON(?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $ip);
    $stmt->execute();
    $stmt->close();
}

function bruteforce_fail_delay(): void
{
    $cfg = bruteforce_limits();
    $ms = (int) $cfg['fail_delay_ms'];
    if ($ms > 0) {
        usleep($ms * 1000);
    }
}



function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Please login first.');
        redirect('/pages/login.php');
    }
}

function require_role(string $role): void
{
    require_login();
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function auth_login(string $username, string $password): bool
{
    $conn = db();
    $ip = client_ip();

    // Lockout check first
    if ($username !== '' && $ip !== '' && bruteforce_is_locked($conn, $username, $ip)) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    // Missing user counts as a failure
    if (!$row) {
        if ($username !== '' && $ip !== '') {
            bruteforce_register_failure($conn, $username, $ip);
            bruteforce_fail_delay();
        }
        return false;
    }

    // Wrong password counts as a failure
    if (!password_verify($password, $row['password_hash'])) {
        if ($username !== '' && $ip !== '') {
            bruteforce_register_failure($conn, $username, $ip);
            bruteforce_fail_delay();
        }
        return false;
    }

    // Success: clear attempts
    if ($username !== '' && $ip !== '') {
        bruteforce_clear($conn, $username, $ip);
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'role' => $row['role'],
    ];

    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }
    session_destroy();
}
