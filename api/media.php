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

function rowToItem(array $row): array {
    $row['tags']     = json_decode($row['tags'], true) ?? [];
    $row['favorite'] = (bool)$row['favorite'];
    return $row;
}

if ($method === 'GET') {
    $rows = $db->query("SELECT * FROM media ORDER BY date DESC, created_at DESC")->fetchAll();
    echo json_encode(array_map('rowToItem', $rows), JSON_UNESCAPED_UNICODE);

} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d || empty($d['type']) || empty($d['src'])) {
        http_response_code(400); echo json_encode(['error' => 'Invalid data']); exit;
    }
    $id = uniqid('m', true);
    $stmt = $db->prepare("
        INSERT INTO media (id, type, name, src, thumb, date, size, duration, tags, favorite)
        VALUES (:id,:type,:name,:src,:thumb,:date,:size,:duration,:tags,:favorite)
    ");
    $stmt->execute([
        ':id'       => $id,
        ':type'     => $d['type'],
        ':name'     => $d['name'] ?? '이름 없음',
        ':src'      => $d['src'],
        ':thumb'    => $d['thumb'] ?? null,
        ':date'     => $d['date'] ?? date('Y-m-d'),
        ':size'     => $d['size'] ?? '0 MB',
        ':duration' => $d['duration'] ?? null,
        ':tags'     => json_encode($d['tags'] ?? [], JSON_UNESCAPED_UNICODE),
        ':favorite' => 0,
    ]);
    $row = $db->query("SELECT * FROM media WHERE id=" . $db->quote($id))->fetch();
    echo json_encode(rowToItem($row), JSON_UNESCAPED_UNICODE);

} elseif ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d || empty($d['id'])) {
        http_response_code(400); echo json_encode(['error' => 'Missing id']); exit;
    }

    if (array_key_exists('name', $d)) {
        // 전체 정보 수정
        $stmt = $db->prepare("UPDATE media SET name=:name, tags=:tags, date=:date WHERE id=:id");
        $stmt->execute([
            ':name' => trim($d['name']),
            ':tags' => json_encode(array_values(array_filter(array_map('trim', $d['tags'] ?? []))), JSON_UNESCAPED_UNICODE),
            ':date' => $d['date'] ?? date('Y-m-d'),
            ':id'   => $d['id'],
        ]);
    } else {
        // 즐겨찾기 토글
        $stmt = $db->prepare("UPDATE media SET favorite=:fav WHERE id=:id");
        $stmt->execute([':fav' => $d['favorite'] ? 1 : 0, ':id' => $d['id']]);
    }

    $row = $db->query("SELECT * FROM media WHERE id=" . $db->quote($d['id']))->fetch();
    echo json_encode($row ? rowToItem($row) : ['ok' => true], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400); echo json_encode(['error' => 'Missing id']); exit;
    }

    $row = $db->query("SELECT src, thumb FROM media WHERE id=" . $db->quote($id))->fetch();
    if ($row) {
        foreach ([$row['src'], $row['thumb']] as $path) {
            if ($path && str_starts_with($path, 'uploads/')) {
                $full = __DIR__ . '/../' . $path;
                if (file_exists($full)) unlink($full);
            }
        }
        $db->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
    }
    echo json_encode(['ok' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
