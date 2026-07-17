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
    $row['user_id']  = isset($row['user_id']) ? (int)$row['user_id'] : null;
    return $row;
}

// 콘텐츠를 등록한 본인이거나 관리자만 수정/삭제할 수 있다.
// (소유자가 없는 기존 데이터는 관리자만 수정/삭제 가능)
function canModifyMedia(PDO $db, array $row): bool {
    if ((int)($row['user_id'] ?? 0) === (int)$_SESSION['user_id']) return true;
    return isAdminUser($db, (int)$_SESSION['user_id']);
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
        INSERT INTO media (id, type, name, src, thumb, date, size, duration, tags, favorite, project_id, user_id)
        VALUES (:id,:type,:name,:src,:thumb,:date,:size,:duration,:tags,:favorite,:project_id,:user_id)
    ");
    $stmt->execute([
        ':id'         => $id,
        ':type'       => $d['type'],
        ':name'       => $d['name'] ?? '이름 없음',
        ':src'        => $d['src'],
        ':thumb'      => $d['thumb'] ?? null,
        ':date'       => $d['date'] ?? date('Y-m-d'),
        ':size'       => $d['size'] ?? '0 MB',
        ':duration'   => $d['duration'] ?? null,
        ':tags'       => json_encode($d['tags'] ?? [], JSON_UNESCAPED_UNICODE),
        ':favorite'   => 0,
        ':project_id' => $d['project_id'] ?? null,
        ':user_id'    => $_SESSION['user_id'],
    ]);
    $row = $db->query("SELECT * FROM media WHERE id=" . $db->quote($id))->fetch();
    echo json_encode(rowToItem($row), JSON_UNESCAPED_UNICODE);

} elseif ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d || empty($d['id'])) {
        http_response_code(400); echo json_encode(['error' => 'Missing id']); exit;
    }

    if (array_key_exists('name', $d)) {
        // 전체 정보 수정: 본인이 등록한 콘텐츠이거나 관리자만 가능
        $row = $db->query("SELECT user_id FROM media WHERE id=" . $db->quote($d['id']))->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error' => '미디어를 찾을 수 없습니다']); exit; }
        if (!canModifyMedia($db, $row)) {
            http_response_code(403);
            echo json_encode(['error' => '본인이 등록한 콘텐츠만 수정할 수 있습니다']);
            exit;
        }

        $stmt = $db->prepare("UPDATE media SET name=:name, tags=:tags, date=:date, project_id=:project_id WHERE id=:id");
        $stmt->execute([
            ':name'       => trim($d['name']),
            ':tags'       => json_encode(array_values(array_filter(array_map('trim', $d['tags'] ?? []))), JSON_UNESCAPED_UNICODE),
            ':date'       => $d['date'] ?? date('Y-m-d'),
            ':project_id' => $d['project_id'] ?? null,
            ':id'         => $d['id'],
        ]);
    } elseif (array_key_exists('project_id', $d)) {
        // 프로젝트 이동만: 콘텐츠 수정이 아닌 갤러리 정리이므로
        // (즐겨찾기와 마찬가지로) 로그인한 회원이면 누구나 가능
        $stmt = $db->prepare("UPDATE media SET project_id=:project_id WHERE id=:id");
        $stmt->execute([':project_id' => $d['project_id'], ':id' => $d['id']]);
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

    $row = $db->query("SELECT src, thumb, user_id FROM media WHERE id=" . $db->quote($id))->fetch();
    if ($row) {
        if (!canModifyMedia($db, $row)) {
            http_response_code(403);
            echo json_encode(['error' => '본인이 등록한 콘텐츠만 삭제할 수 있습니다']);
            exit;
        }
        foreach ([$row['src'], $row['thumb']] as $path) {
            if ($path && str_starts_with($path, 'uploads/')) {
                $full = __DIR__ . '/../' . $path;
                if (file_exists($full)) unlink($full);
            }
        }
        $db->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
        $db->prepare("DELETE FROM comments WHERE media_id=?")->execute([$id]);
    }
    echo json_encode(['ok' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
