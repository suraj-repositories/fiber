<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

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
 
$name       = trim($input['name'] ?? '');
$device_key = trim($input['device_key'] ?? '');
$value      = trim($input['value'] ?? '');
$status     = trim($input['status'] ?? 'active');
 
if ($name === '' || $device_key === '' || $value === '') {
    echo json_encode([
        'success' => false,
        'message' => 'name, device_key, and value are required.'
    ]);
    exit;
}
 
$checkQuery = "SELECT id FROM devices WHERE device_key = ? LIMIT 1";
$checkStmt  = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 's', $device_key);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Device key already exists!'
    ]);
    exit;
}
 
$insertQuery = "
    INSERT INTO devices (user_id, name, device_key, value, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
";

$stmt = mysqli_prepare($conn, $insertQuery);
mysqli_stmt_bind_param($stmt, 'issss', $user_id, $name, $device_key, $value, $status);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Device created successfully!',
        'device' => [
            'id'         => mysqli_insert_id($conn),
            'user_id'    => $user_id,
            'name'       => $name,
            'device_key' => $device_key,
            'value'      => $value,
            'status'     => $status
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Device creation failed.',
        'error'   => mysqli_error($conn)
    ]);
}

exit;
