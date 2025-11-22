<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$device_key = trim($input['device_key'] ?? '');

if ($device_key === '') {
    echo json_encode([
        'success' => false,
        'message' => 'device_key is required.'
    ]);
    exit;
}

$query = "SELECT id FROM devices WHERE user_id = ? AND device_key = ? LIMIT 1";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'is', $user_id, $device_key);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$device = mysqli_fetch_assoc($result);

if (!$device) {
    echo json_encode([
        'success' => false,
        'message' => 'Device not found.'
    ]);
    exit;
}

$device_id = $device['id'];

$deleteStmt = mysqli_prepare($conn, "DELETE FROM devices WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($deleteStmt, 'ii', $device_id, $user_id);
mysqli_stmt_execute($deleteStmt);

echo json_encode([
    'success' => true,
    'message' => 'Device deleted successfully.',
    'data' => [
        'deleted_device_id' => $device_id
    ]
]);

exit;
