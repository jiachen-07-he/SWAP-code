<?php
require_once __DIR__ . '/config/db.php';

$conn = db();

// Fix staff password
$staffPass = 'Staff123!';
$staffHash = password_hash($staffPass, PASSWORD_DEFAULT);

$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
$username = 'staff';
$stmt->bind_param('ss', $staffHash, $username);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo "Staff password updated. Rows affected: $affected\n";
echo "Staff password is now: Staff123!\n";

// Verify it works
$stmt2 = $conn->prepare('SELECT password_hash FROM users WHERE username = ?');
$stmt2->bind_param('s', $username);
$stmt2->execute();
$res = $stmt2->get_result();
$row = $res->fetch_assoc();
$stmt2->close();

$verify = password_verify($staffPass, $row['password_hash']);
echo "Verification test: " . ($verify ? 'SUCCESS' : 'FAILED') . "\n";