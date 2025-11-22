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

$name = trim($input['name'] ?? '');
$sub_title = trim($input['sub_title'] ?? '');
$device_key = trim($input['device_key'] ?? '');
$value = trim($input['value'] ?? '');
$values = $input['values'] ?? [];
$status = trim($input['status'] ?? 'active');
$value_type = trim($input['value_type'] ?? '');
$point_time = trim($input['point_time'] ?? '');
$minute_time = $input['minute_time'] ?? null;

if ($name === '' || $device_key === '' || $value_type === '') {
    echo json_encode(['success' => false, 'message' => 'name, device_key and value_type are required']);
    exit;
} 

$checkQuery = "SELECT id FROM devices WHERE device_key = ? AND user_id = ? LIMIT 1";
$checkStmt  = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 'si', $device_key, $user_id);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    echo json_encode(['success' => false, 'message' => 'Device key already exists!']);
    exit;
} 

$typeQuery = "SELECT id, type_key FROM device_types WHERE type_key = ? LIMIT 1";
$typeStmt  = mysqli_prepare($conn, $typeQuery);
mysqli_stmt_bind_param($typeStmt, 's', $value_type);
mysqli_stmt_execute($typeStmt);

$result = mysqli_stmt_get_result($typeStmt);
$deviceType = mysqli_fetch_assoc($result);

if (!$deviceType) {
    echo json_encode(['success' => false, 'message' => 'Invalid value type!']);
    exit;
}

$type = $deviceType['type_key'];
$device_type_id = $deviceType['id'];
 
$valid = true;
$validationMessage = "";

switch ($type) {

    case 'toggle':
        if (empty($value)) {
            $valid = false;
            $validationMessage = "The value field is required!";
        } else if ($value !== 'on' && $value !== 'off') {
            $valid = false;
            $validationMessage = "Invalid toggle value — must be 'on' or 'off'.";
        }
        break;

    case 'progress':
        if (!is_numeric($value)) {
            $valid = false;
            $validationMessage = "Progress value must be numeric.";
        } else {
            $num = (int)$value;
            if ($num < 0 || $num > 100) {
                $valid = false;
                $validationMessage = "Progress value must be between 0–100.";
            }
        }
        break;

    case 'radio':
        if (empty($values)) {
            $valid = false;
            $validationMessage = "Radio values cannot be empty.";
        }
        break;

    case 'checkbox':
        if (empty($values)) {
            $valid = false;
            $validationMessage = "Checkbox values cannot be empty.";
        }
        break;

    case 'timeout':
        if (empty($point_time)) {
            $valid = false;
            $validationMessage = "Timeout value must be valid.";
        }
        $value = '0';
        break;

    case 'interval':
        if (empty($point_time)) {
            $valid = false;
            $validationMessage = "Interval start time is required.";
        } else if (empty($minute_time) || !is_numeric($minute_time)) {
            $valid = false;
            $validationMessage = "Interval minutes must be numeric.";
        }
        $value = '0';
        break;
    case 'custom': 
        if(empty($value)){
            $validationMessage = "The value should not be empty!";
        }
        break;

    default:
        $valid = false;
        $validationMessage = "Unknown device type.";
        break;
}

if (!$valid) {
    echo json_encode(['success' => false, 'message' => $validationMessage]);
    exit;
}
 
$valuesArray = !empty($values) ? json_encode($values) : null;
$point_time = !empty($point_time) ? $point_time : null;

$insertQuery = "
    INSERT INTO devices 
    (user_id, name, sub_title, device_key, device_type_id, value, point_time, minute_time, allowed_values, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
";

$stmt = mysqli_prepare($conn, $insertQuery);

mysqli_stmt_bind_param(
    $stmt,
    'isssississ',
    $user_id,
    $name,
    $sub_title,
    $device_key,
    $device_type_id,
    $value,
    $point_time,
    $minute_time,
    $valuesArray,
    $status
);


if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Device created successfully!',
        'data' => [
            'id' => mysqli_insert_id($conn),
            'user_id' => $user_id,
            'name' => $name,
            'device_key' => $device_key,
            'device_type_id' => $device_type_id,
            'value' => $value,
            'point_time' => $point_time,
            'minute_time' => $minute_time,
            'allowed_values' => count($values ?? []) > 0 ? json_decode($valuesArray) : null,
            'status' => $status
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Device creation failed.',
        'data' => ['error' => mysqli_error($conn)]
    ]);
}

exit;

