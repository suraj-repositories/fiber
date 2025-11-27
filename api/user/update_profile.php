<?php

header('Content-Type: application/json');

include('../../partials/dbconfig.php');
include('../../partials/jwtVerify.php');
include('../../partials/fileservice.php');
include('../../partials/validation.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$userQuery = "SELECT * FROM users WHERE id = ? LIMIT 1";
$userStmt  = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($userStmt, 'i', $user_id);
mysqli_stmt_execute($userStmt);

$result = mysqli_stmt_get_result($userStmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid value type!']);
    exit;
}

$username = $_POST['username'];
$email = $_POST['email'];
$name = $_POST['name'];
$image = $user['image'];

if(!isUnique('users', ['username' => $username], ['id' => $user['id']])){
     echo json_encode(['success' => false, 'message' => 'Username must be unique!']);
    exit;
} 

if (isset($_FILES["image"])) {
    if (!(isset($_FILES["image"]) && $_FILES["image"]["error"] == 0)) {
        echo json_encode(['success' => false, 'message' => 'Invalid value type!']);
        exit;
    }

    $file_mime_type = mime_content_type($_FILES['image']["tmp_name"]);
    $allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($file_mime_type, $allowed_mime_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, JPG, and PNG are allowed.']);
        exit();
    }

    $uploadedFile = uploadFile($_FILES["image"], $directory = 'profile');
    if ($uploadedFile) {
        deleteIfExists($user['image']);
    }

    $image = $uploadedFile;
}

$updateQuery = "
    UPDATE users SET
    name = ?,
    username = ?,
    email = ?,
    image = ?
    WHERE id = ?
";


$stmt = mysqli_prepare($conn, $updateQuery);

mysqli_stmt_bind_param(
    $stmt,
    'ssssi',
    $name,
    $username,
    $email,
    $image,
    $user_id
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'data' => [
            'id'             => $user['id'],
            'name'           => $name,
            'username'       => $username,
            'image'          => storage_url($image, '/assets/images/default-user.png'),
            'email'          => $email
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Profile update failed.',
        'error'   => mysqli_error($conn)
    ]);
}

exit;
