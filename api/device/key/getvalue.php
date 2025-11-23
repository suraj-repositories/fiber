<?php

header('Content-Type: application/json');
include('../../../partials/dbconfig.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
} 

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($apiKey === '') {
    echo json_encode(['success' => false, 'message' => 'Missing API key']);
    exit;
}
 
$sql = "SELECT user_id FROM api_keys WHERE api_key = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $apiKey);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($res)) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

$user_id = $row['user_id']; 

$singleKey = trim($_GET['device_key'] ?? '');
$multipleKeys = $_GET['device_keys'] ?? null;

if ($multipleKeys !== null && !is_array($multipleKeys)) {
    $multipleKeys = explode(',', $multipleKeys);
}

$keysToFetch = [];

if ($singleKey !== '') $keysToFetch[] = $singleKey;

if (is_array($multipleKeys)) {
    foreach ($multipleKeys as $k) {
        $k = trim($k);
        if ($k !== '') $keysToFetch[] = $k;
    }
}

$keysToFetch = array_unique($keysToFetch);

if (empty($keysToFetch)) {
    echo json_encode(['success' => false, 'message' => 'device_key or device_keys required']);
    exit;
} 

$placeholders = implode(',', array_fill(0, count($keysToFetch), '?'));
$types = str_repeat('s', count($keysToFetch)) . 'i';

$query = "
    SELECT devices.id AS d_id, device_key, value, device_types.type_key AS type,
           point_time, minute_time
    FROM devices
    INNER JOIN device_types ON devices.device_type_id = device_types.id
    WHERE device_key IN ($placeholders) AND user_id = ?
";

$stmt = mysqli_prepare($conn, $query);
$params = $keysToFetch;
$params[] = $user_id;

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
 

$responseData = [];

while ($device = mysqli_fetch_assoc($result)) {

    $value = $device['value'];
    $deviceType = $device['type'];
 
    if ($deviceType == 'timeout') {
        if (date("Y-m-d H:i:00") == $device['point_time']) {
            $updateQuery = "UPDATE devices SET value='0', updated_at=NOW() WHERE id=?";
            $stmtUp = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($stmtUp, 'i', $device['d_id']);
            mysqli_stmt_execute($stmtUp);
            $value = 1;
        }
    } 
    else if ($deviceType == 'interval') {

        $pointTime = $device['point_time'];
        $intervalMins = intval($device['minute_time']);

        $now = strtotime(date("Y-m-d H:i:00"));
        $start = strtotime($pointTime);

        if ($intervalMins > 0 && $start !== false) {

            $diff = $now - $start;

            if ($diff >= 0) {
                $intervalCount = floor($diff / ($intervalMins * 60));
                $nextTrigger = $start + ($intervalCount * $intervalMins * 60);

                if ($now == $nextTrigger) {

                    $updateQuery = "UPDATE devices SET value='1', updated_at=NOW() WHERE id=?";
                    $stmtUp = mysqli_prepare($conn, $updateQuery);
                    mysqli_stmt_bind_param($stmtUp, 'i', $device['d_id']);
                    mysqli_stmt_execute($stmtUp);

                    $value = 1;
                }
            }
        }
    }

    $responseData[$device['device_key']] = $value;
}
 

echo json_encode([
    'success' => true,
    'message' => 'Values fetched successfully',
    'data' => $responseData
]);

exit;
