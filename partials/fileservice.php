<?php

define('STORAGE', realpath(__DIR__ . "/../storage") . DIRECTORY_SEPARATOR);
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();


function generate_uuid_v4()
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function uploadFile($file, $directory = 'uploads', $disk = 'public')
{
    if (!isset($file["name"]) || !isset($file["tmp_name"])) {
        return null;
    }

    $filename = $file["name"];
    $tempname = $file["tmp_name"];

    if (!is_uploaded_file($tempname)) {
        return null;
    }

    $fileInfo = pathinfo($filename);
    $fileExtension = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';

    $newName = generate_uuid_v4() . "_" . time();
    if ($fileExtension !== '') {
        $newName .= "." . $fileExtension;
    }

    $basePath = STORAGE . trim($disk, "/");

    $directory = trim($directory, "/");
    $fullDir = $basePath . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0777, true);
    }

    $finalPath = $fullDir . DIRECTORY_SEPARATOR . $newName;

    if (move_uploaded_file($tempname, $finalPath)) {
        return $disk . "/" . $directory . "/" . $newName;
    }

    return null;
}

function deleteIfExists($path)
{
    $absolutePath = STORAGE . ltrim($path, "/");

    if (file_exists($absolutePath)) {
        return unlink($absolutePath);
    }

    return false;
}

function url($path = '', $default = '/assets/images/placeholder.png')
{
    $placeholder = $default;

    if (!$path || $path === '') {
        return rtrim($_ENV['APP_URL'], '/') . $placeholder;
    }

    $absolute = __DIR__ . '/../public/' . ltrim($path, '/');

    if (!file_exists($absolute)) {
        return rtrim($_ENV['APP_URL'], '/') . $placeholder;
    }

    return rtrim($_ENV['APP_URL'], '/') . '/' . ltrim($path, '/');
}

function storage_url($path = '', $default = '/assets/images/placeholder.png')
{
    $placeholder = $default;
 
    if (!$path || $path === '') {
        return url($placeholder, $default);
    }

    $relative = ltrim($path, '/');
    $absolute = STORAGE . $relative;

    if (!file_exists($absolute)) {
        return url($placeholder, $default);
    }

    return url('storage/' . $relative, $default);
}
