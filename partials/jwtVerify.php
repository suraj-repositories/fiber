<?php
require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
 
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization token missing.'
    ]);
    exit;
}

$jwt = $matches[1];
$user_id = null;
try {
    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_API_KEY'], 'HS256'));
    $user_id = $decoded->id;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token.',
        'error' => $e->getMessage()
    ]);
    exit;
}
