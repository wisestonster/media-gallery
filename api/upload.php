<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$errCode = $_FILES['file']['error'] ?? -1;
if (empty($_FILES['file']) || $errCode !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $msg = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '파일 크기가 너무 큽니다 (최대 50MB)',
        UPLOAD_ERR_NO_FILE  => '파일이 선택되지 않았습니다',
        default             => '업로드에 실패했습니다 (code: ' . $errCode . ')',
    };
    echo json_encode(['error' => $msg]);
    exit;
}

$allowed = ['image/', 'video/', 'audio/'];
$mime = mime_content_type($_FILES['file']['tmp_name']);
$valid = false;
foreach ($allowed as $prefix) {
    if (str_starts_with($mime, $prefix)) { $valid = true; break; }
}
if (!$valid) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
$filename = uniqid('media_', true) . '.' . strtolower($ext);
$dest = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

echo json_encode(['url' => 'uploads/' . $filename, 'mime' => $mime]);
