<?php
/**
 * Security Test Script
 * Run this from command line to verify security measures
 * Usage: php test_security.php
 */

$baseUrl = 'http://localhost/MBELMS_simple';

echo "========================================\n";
echo "   MBELMS Security Test\n";
echo "========================================\n\n";

$tests = [
    'Should be BLOCKED (403)' => [
        "$baseUrl/check_users.php",
        "$baseUrl/test_login.php",
        "$baseUrl/fix_staff_password.php",
        "$baseUrl/config/app.php",
        "$baseUrl/config/db.php",
        "$baseUrl/lib/helpers.php",
        "$baseUrl/database.sql",
        "$baseUrl/_utilities/",
    ],
    'Should WORK (200)' => [
        "$baseUrl/index.php",
        "$baseUrl/pages/login.php",
        "$baseUrl/robots.txt",
    ],
];

function testUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

foreach ($tests as $category => $urls) {
    echo "$category:\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($urls as $url) {
        $code = testUrl($url);
        $status = '';
        $icon = '';

        if ($category === 'Should be BLOCKED (403)') {
            if ($code == 403 || $code == 404) {
                $status = 'PASS';
                $icon = '✓';
            } else {
                $status = 'FAIL - SECURITY RISK!';
                $icon = '✗';
            }
        } else {
            if ($code == 200) {
                $status = 'PASS';
                $icon = '✓';
            } else {
                $status = 'FAIL';
                $icon = '✗';
            }
        }

        printf("%s [%d] %-40s %s\n", $icon, $code, basename($url), $status);
    }
    echo "\n";
}

echo "========================================\n";
echo "Test complete!\n";
echo "========================================\n";
