<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$getQuery = "
SELECT devices.*,device_types.name as device_type_name, device_types.type_key as value_type FROM devices
INNER JOIN device_types ON devices.device_type_id = device_types.id
 WHERE user_id = ? 
 ORDER BY name ASC LIMIT 200
";
$stmt = mysqli_prepare($conn, $getQuery);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$devices = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'message' => 'Devices fetched successfully!',
    'data' => $devices
]);

exit;
