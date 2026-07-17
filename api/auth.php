<?php
require_once __DIR__ . '/session.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
$db = getDb();

// Node.js(ethers.js)로 personal_sign 서명을 검증한다.
// 표준입력으로 데이터를 전달하므로 셸 인젝션 위험이 없다.
function verifyWalletSignature(string $address, string $message, string $signature): bool {
    $script = __DIR__ . '/../verify/verify-sig.js';
    $process = proc_open(
        ['node', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        return false;
    }

    fwrite($pipes[0], json_encode(['address' => $address, 'message' => $message, 'signature' => $signature]));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $result = json_decode($output ?: '', true);
    return !empty($result['valid']);
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {
    case 'register':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 20) {
            http_response_code(400);
            echo json_encode(['error' => '아이디는 3~20자여야 합니다']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            echo json_encode(['error' => '아이디는 영문, 숫자, 밑줄만 사용 가능합니다']);
            exit;
        }
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => '비밀번호는 6자 이상이어야 합니다']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => '이미 사용 중인 아이디입니다']);
            exit;
        }

        $newUser = insertUser($db, $username, password_hash($password, PASSWORD_DEFAULT));

        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['ok' => true, 'username' => $username]);
        break;

    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => '아이디 또는 비밀번호가 올바르지 않습니다']);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['ok' => true, 'username' => $username]);
        break;

    case 'wallet_nonce':
        $address = trim($input['wallet_address'] ?? '');
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            http_response_code(400);
            echo json_encode(['error' => '올바르지 않은 지갑 주소입니다']);
            exit;
        }
        $addressKey = strtolower($address);

        $nonce   = bin2hex(random_bytes(16));
        $message = "미디어 갤러리 로그인 인증\n\n지갑 주소: {$address}\nNonce: {$nonce}\n발급 시각: " . gmdate('c');

        $stmt = $db->prepare("
            INSERT INTO wallet_nonces (wallet_address, nonce, message, created_at)
            VALUES (:addr, :nonce, :msg, datetime('now'))
            ON CONFLICT(wallet_address) DO UPDATE SET
                nonce = excluded.nonce, message = excluded.message, created_at = excluded.created_at
        ");
        $stmt->execute([':addr' => $addressKey, ':nonce' => $nonce, ':msg' => $message]);

        echo json_encode(['message' => $message]);
        break;

    case 'wallet_login':
        $address   = trim($input['wallet_address'] ?? '');
        $signature = $input['signature'] ?? '';

        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address) || !is_string($signature) || $signature === '') {
            http_response_code(400);
            echo json_encode(['error' => '잘못된 요청입니다']);
            exit;
        }
        $addressKey = strtolower($address);

        $stmt = $db->prepare("
            SELECT message FROM wallet_nonces
            WHERE wallet_address = ? AND created_at >= datetime('now', '-5 minutes')
        ");
        $stmt->execute([$addressKey]);
        $row = $stmt->fetch();

        // 1회성 nonce: 성공/실패와 무관하게 즉시 폐기해 재사용을 막는다.
        $db->prepare("DELETE FROM wallet_nonces WHERE wallet_address = ?")->execute([$addressKey]);

        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => '인증 요청을 찾을 수 없거나 만료되었습니다. 다시 시도해주세요']);
            exit;
        }

        if (!verifyWalletSignature($address, $row['message'], $signature)) {
            http_response_code(401);
            echo json_encode(['error' => '서명 검증에 실패했습니다']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, username FROM users WHERE wallet_address = ?");
        $stmt->execute([$addressKey]);
        $user = $stmt->fetch();

        if ($user) {
            $userId   = $user['id'];
            $username = $user['username'];
        } else {
            // 지갑 주소(0x+40자)는 일반 아이디(최대 20자)와 절대 겹치지 않는다.
            $username = $addressKey;
            $newUser  = insertUser($db, $username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $addressKey);
            $userId   = $newUser['id'];
        }

        session_regenerate_id(true);
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;
        echo json_encode(['ok' => true, 'username' => $username]);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
