<?php
// Unauthenticated endpoints used by the login screen only.
// Must never expose password hashes or anything else sensitive.
require_once 'config/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'listEmployees':
        $stmt = $pdo->query("SELECT username, full_name FROM users WHERE role = 'employee' ORDER BY full_name");
        echo json_encode($stmt->fetchAll());
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
