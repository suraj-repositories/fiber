<?php
include('../../partials/dbconfig.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON format.'
    ]);
    exit;
}

$name = trim($input['name'] ?? '');
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($name === '' || $username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit;
}

$checkQuery = "SELECT id FROM users WHERE username = ? LIMIT 1";
$checkStmt  = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 's', $username);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Username already exists!'
    ]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$insertQuery = "INSERT INTO users (name, username, password, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())";

$insertStmt = mysqli_prepare($conn, $insertQuery);
mysqli_stmt_bind_param($insertStmt, 'sss', $name, $username, $hashedPassword);

if (mysqli_stmt_execute($insertStmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed.',
        'error'   => mysqli_error($conn)
    ]);
}

exit;
