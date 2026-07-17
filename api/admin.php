<?php
require_once __DIR__ . '/session.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
$db    = getDb();
$admin = requireAdmin($db); // 이 파일의 모든 엔드포인트는 관리자 전용이다.

$resource = $_GET['resource'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

function dirSize(string $dir): int {
    $size = 0;
    foreach ((glob($dir . '/*') ?: []) as $file) {
        if (is_file($file)) $size += filesize($file);
    }
    return $size;
}

function countAdmins(PDO $db): int {
    return (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

if ($resource === 'stats') {
    if ($method !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

    $mediaByType = [];
    foreach ($db->query("SELECT type, COUNT(*) AS c FROM media GROUP BY type")->fetchAll() as $row) {
        $mediaByType[$row['type']] = (int)$row['c'];
    }

    echo json_encode([
        'users'          => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'admins'         => countAdmins($db),
        'media'          => (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn(),
        'media_by_type'  => $mediaByType,
        'projects'       => (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
        'comments'       => (int)$db->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
        'storage_bytes'  => dirSize(__DIR__ . '/../uploads'),
    ], JSON_UNESCAPED_UNICODE);

} elseif ($resource === 'users') {
    if ($method === 'GET') {
        $rows = $db->query("
            SELECT u.id, u.username, u.role, u.created_at,
                   (u.wallet_address IS NOT NULL) AS has_wallet,
                   (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comment_count
            FROM users u
            ORDER BY u.created_at ASC
        ")->fetchAll();
        foreach ($rows as &$r) {
            $r['id']            = (int)$r['id'];
            $r['has_wallet']    = (bool)$r['has_wallet'];
            $r['comment_count'] = (int)$r['comment_count'];
        }
        echo json_encode(['current_user_id' => (int)$admin['id'], 'users' => $rows], JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'PUT') {
        $d      = json_decode(file_get_contents('php://input'), true);
        $id     = (int)($d['id'] ?? 0);
        $role   = $d['role'] ?? '';

        if (!$id || !in_array($role, ['user', 'admin'], true)) {
            http_response_code(400);
            echo json_encode(['error' => '요청이 올바르지 않습니다']);
            exit;
        }
        if ($id === (int)$admin['id']) {
            http_response_code(400);
            echo json_encode(['error' => '본인의 권한은 변경할 수 없습니다']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => '사용자를 찾을 수 없습니다']);
            exit;
        }

        $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
        echo json_encode(['ok' => true, 'id' => $id, 'role' => $role]);

    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        if ($id === (int)$admin['id']) {
            http_response_code(400);
            echo json_encode(['error' => '본인 계정은 삭제할 수 없습니다']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => '사용자를 찾을 수 없습니다']);
            exit;
        }

        $db->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} elseif ($resource === 'comments') {
    if ($method === 'GET') {
        $rows = $db->query("
            SELECT c.id, c.content, c.username, c.user_id, c.created_at, c.media_id, m.name AS media_name
            FROM comments c
            LEFT JOIN media m ON m.id = c.media_id
            ORDER BY c.created_at DESC
            LIMIT 200
        ")->fetchAll();
        foreach ($rows as &$r) { $r['user_id'] = (int)$r['user_id']; }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $db->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown resource']);
}
