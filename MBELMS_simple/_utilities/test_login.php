<?php
require_once __DIR__ . '/config/db.php';

$username = 'admin';
$password = 'Admin123!';

$conn = db();
$stmt = $conn->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

echo "Testing login for: $username\n";
echo "User found: " . ($row ? 'Yes' : 'No') . "\n";

if ($row) {
    echo "Username from DB: " . $row['username'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Password hash: " . $row['password_hash'] . "\n";
    echo "Password verify result: " . (password_verify($password, $row['password_hash']) ? 'SUCCESS' : 'FAILED') . "\n";

    // Test with staff too
    echo "\n--- Testing staff account ---\n";
    $username2 = 'staff';
    $password2 = 'Staff123!';

    $stmt2 = $conn->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt2->bind_param('s', $username2);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $row2 = $res2->fetch_assoc();
    $stmt2->close();

    echo "Staff user found: " . ($row2 ? 'Yes' : 'No') . "\n";
    if ($row2) {
        echo "Password verify result: " . (password_verify($password2, $row2['password_hash']) ? 'SUCCESS' : 'FAILED') . "\n";
    }
}
