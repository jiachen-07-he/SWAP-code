<?php
require_once __DIR__ . '/config/db.php';

try {
    $db = db();
    $result = $db->query('SELECT username, role FROM users');
    echo "Users in database:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['username'] . " (" . $row['role'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
