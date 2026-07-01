<?php
require_once __DIR__ . '/session.php';
startSecureSession();
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

// 확장자는 신뢰할 수 없는 원본 파일명이 아니라, 실제 검증된 MIME 타입에서만 결정한다.
// (그렇지 않으면 이미지 시그니처 뒤에 PHP 코드를 붙인 폴리글랏 파일을 .php로 업로드해
//  코드 실행으로 이어질 수 있음)
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
    'video/quicktime' => 'mov',
    'audio/mpeg' => 'mp3',
    'audio/wav'  => 'wav',
    'audio/ogg'  => 'ogg',
    'audio/mp4'  => 'm4a',
];

$mime = mime_content_type($_FILES['file']['tmp_name']);
if (!isset($mimeToExt[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = $mimeToExt[$mime];
$filename = uniqid('media_', true) . '.' . $ext;
$dest = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

echo json_encode(['url' => 'uploads/' . $filename, 'mime' => $mime]);
