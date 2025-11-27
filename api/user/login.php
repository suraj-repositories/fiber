<?php
include('../../partials/dbconfig.php');
include('../../partials/fileservice.php');
require '../../vendor/autoload.php';

use Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format.']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

$payload = [
    'id'       => $user['id'],
    'username' => $user['username'],
    'iat'      => time(),
    'exp'      => time() + 86400
];

$secretKey = $_ENV['JWT_API_KEY'];

$jwt = JWT::encode($payload, $secretKey, 'HS256');

echo json_encode([
    'success' => true,
    'message' => 'Login successful!',
    'token'   => $jwt,
    'user'    => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'image' => storage_url($user['image'], '/assets/images/default-user.png'),
        'name' => $user['name']
    ]
]);

exit;
