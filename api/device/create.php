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
$device_icons_id = $input['device_icons_id'] ?? null;

$run_type = $input['run_type'] ?? 'manual';
$run_duration = $input['run_duration'] ?? null;
$run_until_time = $input['run_until_time'] ?? null;

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

if (!empty($device_icons_id)) {
    $iconQuery = "SELECT * FROM device_icons WHERE id = ? LIMIT 1";
    $iconStmt  = mysqli_prepare($conn, $iconQuery);
    mysqli_stmt_bind_param($iconStmt, 'i', $device_icons_id);
    mysqli_stmt_execute($iconStmt);

    $result = mysqli_stmt_get_result($iconStmt);
    $deviceicon = mysqli_fetch_assoc($result);

    if (!$deviceicon) {
        echo json_encode(['success' => false, 'message' => 'Invalid icon!']);
        exit;
    }
}

$type = $deviceType['type_key'];
$device_type_id = $deviceType['id'];

$valid = true;
$validationMessage = "";

switch ($type) {

    case 'toggle':
        if (empty($value)) {
            $value = 'off';
        } else if ($value !== 'on' && $value !== 'off') {
            $valid = false;
            $validationMessage = "Invalid toggle value — must be 'on' or 'off'.";
        }
        break;

    case 'progress':
        $value = 0;
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
        if (empty($run_type)) {
            $valid = false;
            $validationMessage = "Run type is required";
        }
        if ($run_type == 'run_for_duration' && empty($run_duration)) {
            $valid = false;
            $validationMessage = "Run Deration field is requrired";
        }
        if ($run_type == 'run_until_time' && empty($run_until_time)) {
            $valid = false;
            $validationMessage = "Run until time is required!";
        }
        if (!empty($run_until_time)) {
            $pointTs = strtotime($point_time);
            $runUntilTs = strtotime($run_until_time);

            if ($runUntilTs === false || $pointTs === false) {
                $valid = false;
                $validationMessage = "Invalid date/time format.";
            } elseif ($runUntilTs <= $pointTs) {
                $valid = false;
                $validationMessage = "Run until time must be greater than point time.";
            }
        }

        if (!in_array($run_type, ['run_for_duration', 'run_until_time', 'run_once'])) {
            $valid = false;
            $validationMessage = 'Invalid run type!';
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
        if (empty($run_type)) {
            $valid = false;
            $validationMessage = "Run type is required";
        }
        if ($run_type == 'run_for_duration' && empty($run_duration)) {
            $valid = false;
            $validationMessage = "Run Deration field is requrired";
        } 
        if (!in_array($run_type, ['run_for_duration', 'run_once'])) {
            $valid = false;
            $validationMessage = 'Invalid run type!';
        }

        $value = '0';
        break;
    case 'custom':
        if (empty($value)) {
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
    (user_id, name, sub_title, device_key, device_type_id, value, point_time, minute_time, run_type, run_duration, run_until_time, allowed_values, device_icons_id, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
";

$stmt = mysqli_prepare($conn, $insertQuery);

mysqli_stmt_bind_param(
    $stmt,
    'isssissisissis',
    $user_id,
    $name,
    $sub_title,
    $device_key,
    $device_type_id,
    $value,
    $point_time,
    $minute_time,
    $run_type,
    $run_duration,
    $run_until_time,
    $valuesArray,
    $device_icons_id,
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
            'run_type' => $run_type,
            'run_duration' => $run_duration,
            'run_until_time' => $run_until_time,
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
