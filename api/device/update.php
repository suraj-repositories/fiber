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

$device_id  = intval($input['id'] ?? 0);
$name       = trim($input['name'] ?? '');
$sub_title  = trim($input['sub_title'] ?? '');
$device_key = trim($input['device_key'] ?? '');
$value      = trim($input['value'] ?? '');
$values     = $input['values'] ?? [];
$status     = trim($input['status'] ?? 'active');
$value_type = trim($input['value_type'] ?? '');
$point_time = trim($input['point_time'] ?? '');
$minute_time = $input['minute_time'] ?? null;

$run_type = $input['run_type'] ?? 'manual';
$run_duration = $input['run_duration'] ?? null;
$run_until_time = $input['run_until_time'] ?? null;

if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Device ID']);
    exit;
}

if ($name === '' || $device_key === '' || $value_type === '') {
    echo json_encode(['success' => false, 'message' => 'name, device_key and value_type are required']);
    exit;
}

$deviceQuery = "SELECT id FROM devices WHERE id = ? AND user_id = ? LIMIT 1";
$deviceStmt = mysqli_prepare($conn, $deviceQuery);
mysqli_stmt_bind_param($deviceStmt, 'ii', $device_id, $user_id);
mysqli_stmt_execute($deviceStmt);
mysqli_stmt_store_result($deviceStmt);

if (mysqli_stmt_num_rows($deviceStmt) === 0) {
    echo json_encode(['success' => false, 'message' => 'Device not found or unauthorized']);
    exit;
}

$checkQuery = "SELECT id FROM devices WHERE device_key = ? AND user_id = ? AND id != ? LIMIT 1";
$checkStmt  = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 'sii', $device_key, $user_id, $device_id);
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
        if ($value !== 'on' && $value !== 'off') {
            $valid = false;
            $validationMessage = "Invalid toggle value — must be 'on' or 'off'.";
        }
        break;

    case 'progress':
        if (!is_numeric($value) || (int)$value < 0 || (int)$value > 100) {
            $valid = false;
            $validationMessage = "Progress value must be between 0–100.";
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
            $validationMessage = "Timeout must be valid datetime.";
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

        $minute_time = (int) $minute_time;;
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


$updateQuery = "
    UPDATE devices SET
        name = ?,
        sub_title = ?,
        device_key = ?,
        device_type_id = ?,
        value = ?,
        point_time = ?,
        minute_time = ?,
        run_type = ?,
        run_duration = ?,
        run_until_time = ?,
        allowed_values = ?,
        status = ?,
        updated_at = NOW()
    WHERE id = ? AND user_id = ?
";

$stmt = mysqli_prepare($conn, $updateQuery);

mysqli_stmt_bind_param(
    $stmt,
    'sssissisisssii',
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
    $status,
    $device_id,
    $user_id
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Device updated successfully!',
        'data' => [
            'id'             => $device_id,
            'name'           => $name,
            'sub_title'      => $sub_title,
            'device_key'     => $device_key,
            'device_type_id' => $device_type_id,
            'value'          => $value,
            'point_time'     => $point_time,
            'allowed_values' => json_decode($valuesArray),
            'status'         => $status
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Device update failed.',
        'error'   => mysqli_error($conn)
    ]);
}

exit;
