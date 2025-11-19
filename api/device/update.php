<?php
header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format.']);
    exit;
}

$device_key = trim($input['device_key'] ?? '');
$value      = trim($input['value'] ?? '');
$status     = trim($input['status'] ?? '');

if ($device_key === '' || $value === '') {
    echo json_encode(['success' => false, 'message' => 'device_key and value are required.']);
    exit;
}

$query = "SELECT id, value FROM devices WHERE user_id = ? AND device_key = ? LIMIT 1";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'is', $user_id, $device_key);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$device = mysqli_fetch_assoc($result);

if (!$device) {
    echo json_encode(['success' => false, 'message' => 'Device not found.']);
    exit;
}

$before_value = $device['value'];
$device_id    = $device['id'];

$updateQuery = "UPDATE devices SET value = ?, status = ?, updated_at = NOW() WHERE id = ?";
$updateStmt  = mysqli_prepare($conn, $updateQuery);
mysqli_stmt_bind_param($updateStmt, 'ssi', $value, $status, $device_id);
mysqli_stmt_execute($updateStmt);

$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$deviceInfo = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
$browser    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$os         = php_uname('s');
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? '';

$historyQuery = "
    INSERT INTO device_history 
    (user_id, device_id, before_value, after_value, ip, device, browser, os, location, latitude, longitude, user_agent, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', '', ?, NOW(), NOW())
";

$historyStmt = mysqli_prepare($conn, $historyQuery);

mysqli_stmt_bind_param(
    $historyStmt,
    'iisssssss',
    $user_id,
    $device_id,
    $before_value,
    $value,
    $ip,
    $deviceInfo,
    $browser,
    $os,
    $userAgent
);

mysqli_stmt_execute($historyStmt);

echo json_encode([
    'success' => true,
    'message' => 'Device updated successfully.',
    'device' => [
        'device_id'    => $device_id,
        'before_value' => $before_value,
        'after_value'  => $value,
        'status'       => $status
    ]
]);

exit;
