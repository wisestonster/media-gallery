<?php
require_once __DIR__ . '/session.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';
$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];

function rowToComment(array $row): array {
    $row['user_id'] = (int)$row['user_id'];
    return $row;
}

if ($method === 'GET') {
    $mediaId = $_GET['media_id'] ?? '';
    if (!$mediaId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing media_id']);
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM comments WHERE media_id = ? ORDER BY created_at ASC");
    $stmt->execute([$mediaId]);
    echo json_encode(array_map('rowToComment', $stmt->fetchAll()), JSON_UNESCAPED_UNICODE);

} elseif ($method === 'POST') {
    $d       = json_decode(file_get_contents('php://input'), true);
    $mediaId = trim($d['media_id'] ?? '');
    $content = trim($d['content'] ?? '');
    if (!$mediaId || $content === '' || iconv_strlen($content, 'UTF-8') > 500) {
        http_response_code(400);
        echo json_encode(['error' => '댓글 내용을 확인해주세요 (1~500자)']);
        exit;
    }

    $exists = $db->prepare("SELECT 1 FROM media WHERE id = ?");
    $exists->execute([$mediaId]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => '미디어를 찾을 수 없습니다']);
        exit;
    }

    $id   = uniqid('c', true);
    $stmt = $db->prepare("
        INSERT INTO comments (id, media_id, user_id, username, content)
        VALUES (:id, :media_id, :user_id, :username, :content)
    ");
    $stmt->execute([
        ':id'       => $id,
        ':media_id' => $mediaId,
        ':user_id'  => $_SESSION['user_id'],
        ':username' => $_SESSION['username'],
        ':content'  => $content,
    ]);
    $row = $db->query("SELECT * FROM comments WHERE id=" . $db->quote($id))->fetch();
    echo json_encode(rowToComment($row), JSON_UNESCAPED_UNICODE);

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }
    $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => '댓글을 찾을 수 없습니다']);
        exit;
    }
    if ((int)$row['user_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => '본인 댓글만 삭제할 수 있습니다']);
        exit;
    }
    $db->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
