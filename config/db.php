<?php
// The shop operates in Kampala, Uganda (East Africa Time, UTC+3, no DST).
// Fixing this centrally means every date()/time() call in PHP, and every
// NOW()/CURDATE()/CURRENT_TIMESTAMP() in MySQL, agrees on "now" regardless
// of what timezone the actual server (or an employee's browser) is set to.
date_default_timezone_set('Africa/Kampala');

// Harden the session cookie. 'secure' is only enabled when the site is
// actually served over HTTPS, so this stays compatible with local HTTP
// development and automatically locks down once deployed with SSL.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Never display raw PHP/DB errors to visitors in production.
ini_set('display_errors', '0');
error_reporting(E_ALL);

$host = 'localhost';
$dbname = 'trust_hardware_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Fixed offset (not a named zone) so this works even on hosts whose
    // MySQL has no timezone-name tables loaded. Kampala has no DST, so a
    // fixed +03:00 is always correct.
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    error_log('Trust Hardware DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('The system is temporarily unavailable. Please try again shortly.');
}
