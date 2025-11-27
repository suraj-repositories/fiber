<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');
include('../../partials/fileservice.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$userQuery = "SELECT users.*, api_keys.api_key, api_keys.visibility as key_visibility FROM users LEFT JOIN api_keys ON users.id = api_keys.user_id WHERE users.id = ? LIMIT 1";
$userStmt  = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($userStmt, 'i', $user_id);
mysqli_stmt_execute($userStmt);

$result = mysqli_stmt_get_result($userStmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid user!']);
    exit;
}
$user['image'] = storage_url($user['image'], '/assets/images/default-user.png');
 
if(empty($user['key_visibility']) || $user['key_visibility'] != 'visible'){
    unset($user['key_visibility']);
    unset($user['api_key']);
}
echo json_encode([
    'success' => true,
    'message' => 'Profile fetched successful!',
    'data'   => $user,
]);

exit;
