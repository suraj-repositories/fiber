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

$stmt = mysqli_prepare($conn, "SELECT * FROM device_types ORDER BY id ASC"); 
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$types = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'message' => 'Device types fetched successfully!',
    'data' => $types
]);

exit;
