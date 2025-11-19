<?php
header('Content-Type: application/json'); 
require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = $_ENV['DATABASE_HOST'];
$port = $_ENV['DATABASE_PORT'];
$username = $_ENV['DATABASE_USERNAME'];
$password = $_ENV['DATABASE_PASSWORD'];
$database = $_ENV['DATABASE_NAME'];

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => mysqli_connect_error()
    ]);
    exit;
}
