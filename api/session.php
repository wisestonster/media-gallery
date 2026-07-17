<?php
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);
    session_start();
}

// 세션이 관리자 권한을 가졌는지 매 요청마다 DB에서 다시 확인한다.
// (세션에 role을 캐싱하면, 다른 관리자가 이 계정의 권한을 낮춘 뒤에도
//  재로그인 전까지 계속 관리자 권한이 유지되는 문제가 생길 수 있음)
function requireAdmin(PDO $db): array {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => '관리자 권한이 필요합니다']);
        exit;
    }
    return $user;
}
