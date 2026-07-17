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

function validProjectName($name): ?string {
    $name = trim((string)$name);
    if ($name === '' || iconv_strlen($name, 'UTF-8') > 50) return null;
    return $name;
}

if ($method === 'GET') {
    $rows = $db->query("
        SELECT p.id, p.name, p.created_at, COUNT(m.id) AS count
        FROM projects p
        LEFT JOIN media m ON m.project_id = p.id
        GROUP BY p.id
        ORDER BY p.created_at ASC
    ")->fetchAll();
    foreach ($rows as &$r) { $r['count'] = (int)$r['count']; }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} elseif ($method === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true);
    $name = validProjectName($d['name'] ?? '');
    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => '프로젝트 이름을 확인해주세요 (1~50자)']);
        exit;
    }
    $id = uniqid('p', true);
    $db->prepare("INSERT INTO projects (id, name) VALUES (?, ?)")->execute([$id, $name]);
    $row = $db->query("SELECT id, name, created_at FROM projects WHERE id=" . $db->quote($id))->fetch();
    $row['count'] = 0;
    echo json_encode($row, JSON_UNESCAPED_UNICODE);

} elseif ($method === 'PUT') {
    $d    = json_decode(file_get_contents('php://input'), true);
    $id   = $d['id'] ?? '';
    $name = validProjectName($d['name'] ?? '');
    if (!$id || !$name) {
        http_response_code(400);
        echo json_encode(['error' => '요청이 올바르지 않습니다']);
        exit;
    }
    $stmt = $db->prepare("UPDATE projects SET name=? WHERE id=?");
    $stmt->execute([$name, $id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => '프로젝트를 찾을 수 없습니다']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => $id, 'name' => $name], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }
    // 프로젝트에 속한 미디어는 삭제하지 않고 미분류로 되돌린다
    $db->prepare("UPDATE media SET project_id = NULL WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
