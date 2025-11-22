<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$device_key = trim($_GET['device_key'] ?? '');

if ($device_key === '') {
    echo json_encode(['success' => false, 'message' => 'device_key is required']);
    exit;
}
 
$query = "
    SELECT value 
    FROM devices 
    WHERE device_key = ? AND user_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'si', $device_key, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$device = mysqli_fetch_assoc($result);

if (!$device) {
    echo json_encode(['success' => false, 'message' => 'Device not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Value fetched successfully',
    'data' => [
        'device_key' => $device_key,
        'value' => $device['value']
    ]
]);

exit;
