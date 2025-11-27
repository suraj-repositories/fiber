<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');
include('../../partials/fileservice.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$updateQuery = "
    UPDATE api_keys SET
    visibility = 'hidden'
    WHERE user_id = ?
"; 

$stmt = mysqli_prepare($conn, $updateQuery);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $user_id
);
if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Key status updated successfully!',

    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Key status update failed.',
        'error'   => mysqli_error($conn)
    ]);
}

exit;
