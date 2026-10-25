<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$role = $_POST['role'] ?? '';
$employeeUsername = trim($_POST['employee'] ?? '');
$password = $_POST['password'] ?? '';

if (!in_array($role, ['admin', 'employee'], true)) {
    header('Location: index.php?error=1');
    exit();
}

if ($role === 'employee' && $employeeUsername === '') {
    header('Location: index.php?error=1');
    exit();
}

$max_attempts = 5;
$attemptKey = $role === 'admin' ? 'admin' : ('employee:' . $employeeUsername);

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}
if (!isset($_SESSION['login_attempts'][$attemptKey])) {
    $_SESSION['login_attempts'][$attemptKey] = 0;
}

if ($_SESSION['login_attempts'][$attemptKey] >= $max_attempts) {
    header("Location: index.php?lockout=1");
    exit();
}

if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin'");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'employee' AND username = ?");
    $stmt->execute([$employeeUsername]);
}
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['login_attempts'][$attemptKey] = 0;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    header('Location: ' . ($role === 'admin' ? 'admin.php' : 'employee.php'));
    exit();
}

$_SESSION['login_attempts'][$attemptKey]++;
$remaining = max(0, $max_attempts - $_SESSION['login_attempts'][$attemptKey]);
header("Location: index.php?error=1&remaining=$remaining");
exit();
