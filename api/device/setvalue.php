<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
    exit;
}

$device_key = trim($input['device_key'] ?? '');
$value      = trim($input['value'] ?? '');

$ip         = $input['ip'] ?? $_SERVER['REMOTE_ADDR'];
$deviceInfo = $input['device'] ?? '';
$browser    = $input['browser'] ?? '';
$os         = $input['os'] ?? '';
$location   = $input['location'] ?? '';
$lat        = $input['latitude'] ?? '';
$lng        = $input['longitude'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'];

if ($device_key === '') {
    echo json_encode(['success' => false, 'message' => 'device_key is required']);
    exit;
}

$query = "
    SELECT d.id, d.value, d.allowed_values, dt.type_key 
    FROM devices d
    JOIN device_types dt ON d.device_type_id = dt.id
    WHERE d.device_key = ? AND d.user_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'si', $device_key, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$deviceRow = mysqli_fetch_assoc($result);

if (!$deviceRow) {
    echo json_encode(['success' => false, 'message' => 'Device not found']);
    exit;
}

$device_id     = $deviceRow['id'];
$before_value  = $deviceRow['value'];
$type          = $deviceRow['type_key'];
$allowed_values = json_decode($deviceRow['allowed_values'] ?? "[]", true);

$valid = true;
$message = "";

switch ($type) {

    case 'toggle':
        if (!in_array($value, ['on', 'off'])) {
            $valid = false;
            $message = "Value must be 'on' or 'off'.";
        }
        break;

    case 'progress':
        if (!is_numeric($value) || $value < 0 || $value > 100) {
            $valid = false;
            $message = "Progress value must be numeric between 0–100.";
        }
        break;

    case 'radio':
    case 'checkbox':
        if (!in_array($value, $allowed_values)) {
            $valid = false;
            $message = "Invalid value — must match allowed values.";
        }
        break;

    case 'custom':
        if (empty($value)) {
            $valid = false;
            $message = "Value cannot be empty.";
        }
        break;

    case 'timeout':
    case 'interval':
        echo json_encode(['success' => false, 'message' => 'This device type is not editable']);
        exit;

    default:
        $valid = false;
        $message = "Unknown device type.";
        break;
}

if (!$valid) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
 
$updateQuery = "
    UPDATE devices 
    SET value = ?, updated_at = NOW()
    WHERE device_key = ? AND user_id = ?
";

$updateStmt = mysqli_prepare($conn, $updateQuery);
mysqli_stmt_bind_param($updateStmt, 'ssi', $value, $device_key, $user_id);

if (!mysqli_stmt_execute($updateStmt)) {
    echo json_encode([
        'success' => false,
        'message' => 'Update failed',
        'error' => mysqli_error($conn)
    ]);
    exit;
}
 
$historyQuery = "
INSERT INTO device_history (
    user_id, device_id, before_value, after_value,
    ip, device, browser, os, location,
    latitude, longitude, user_agent,
    created_at, updated_at
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
";

$historyStmt = mysqli_prepare($conn, $historyQuery);
mysqli_stmt_bind_param(
    $historyStmt,
    "iissssssssss",
    $user_id,
    $device_id,
    $before_value,
    $value,
    $ip,
    $deviceInfo,
    $browser,
    $os,
    $location,
    $lat,
    $lng,
    $user_agent
);

mysqli_stmt_execute($historyStmt);
 
echo json_encode([
    'success' => true,
    'message' => 'Value updated successfully!',
    'data' => [
        'device_key' => $device_key,
        'old_value'  => $before_value,
        'new_value'  => $value
    ]
]);

exit;
