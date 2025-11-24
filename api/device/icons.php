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

$stmt = mysqli_prepare($conn, "SELECT * FROM device_icons ORDER BY priority_serial_number DESC LIMIT 4"); 
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$icons = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'message' => 'Device icons fetched successfully!',
    'data' => $icons
]);

exit;
