<?php
declare(strict_types=1);

function audit_log(string $action, ?string $entity = null, ?int $entity_id = null): void {
    $conn = db();
    $u = current_user();

    $uid = $u ? (int)$u['id'] : 0;          // 0 => NULL via NULLIF
    $eid = $entity_id ? (int)$entity_id : 0;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip_address, user_agent)
         VALUES (NULLIF(?,0), ?, ?, NULLIF(?,0), ?, ?)'
    );
    $stmt->bind_param('ississ', $uid, $action, $entity, $eid, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}
