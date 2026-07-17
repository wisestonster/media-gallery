<?php
function getDb(): PDO {
    static $db = null;
    if ($db) return $db;

    $path = __DIR__ . '/../data/gallery.db';
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL');

    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT    UNIQUE NOT NULL,
            password TEXT    NOT NULL,
            created_at TEXT  DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS projects (
            id         TEXT PRIMARY KEY,
            name       TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS media (
            id         TEXT    PRIMARY KEY,
            type       TEXT    NOT NULL,
            name       TEXT    NOT NULL,
            src        TEXT    NOT NULL,
            thumb      TEXT,
            date       TEXT    NOT NULL,
            size       TEXT    NOT NULL,
            duration   TEXT,
            tags       TEXT    NOT NULL DEFAULT '[]',
            favorite   INTEGER NOT NULL DEFAULT 0,
            project_id TEXT,
            created_at TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS comments (
            id         TEXT    PRIMARY KEY,
            media_id   TEXT    NOT NULL,
            user_id    INTEGER NOT NULL,
            username   TEXT    NOT NULL,
            content    TEXT    NOT NULL,
            created_at TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS wallet_nonces (
            wallet_address TEXT PRIMARY KEY,
            nonce          TEXT NOT NULL,
            message        TEXT NOT NULL,
            created_at     TEXT DEFAULT (datetime('now'))
        );
    ");

    // 기존 DB(project_id 컬럼 도입 이전)를 위한 마이그레이션
    $cols = $db->query("PRAGMA table_info(media)")->fetchAll();
    $hasProjectId = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'project_id') { $hasProjectId = true; break; }
    }
    if (!$hasProjectId) {
        $db->exec("ALTER TABLE media ADD COLUMN project_id TEXT");
    }

    // 기존 DB(user_id 컬럼 도입 이전)를 위한 마이그레이션.
    // 소유자가 없는(NULL) 기존 미디어는 관리자만 수정/삭제할 수 있다.
    $hasUserId = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'user_id') { $hasUserId = true; break; }
    }
    if (!$hasUserId) {
        $db->exec("ALTER TABLE media ADD COLUMN user_id INTEGER");
    }

    // 기존 DB(wallet_address 컬럼 도입 이전)를 위한 마이그레이션
    $userCols = $db->query("PRAGMA table_info(users)")->fetchAll();
    $hasWalletAddress = false;
    foreach ($userCols as $col) {
        if ($col['name'] === 'wallet_address') { $hasWalletAddress = true; break; }
    }
    if (!$hasWalletAddress) {
        $db->exec("ALTER TABLE users ADD COLUMN wallet_address TEXT");
    }
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_wallet_address ON users(wallet_address) WHERE wallet_address IS NOT NULL");

    // 기존 DB(role 컬럼 도입 이전)를 위한 마이그레이션
    $hasRole = false;
    foreach ($userCols as $col) {
        if ($col['name'] === 'role') { $hasRole = true; break; }
    }
    if (!$hasRole) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
    }

    // 최초 실행 시 샘플 데이터 삽입
    $count = $db->query("SELECT COUNT(*) FROM media")->fetchColumn();
    if ($count == 0) {
        seedMedia($db);
    }

    return $db;
}

// 계정이 하나도 없는 상태(최초 실행)에서 가입하는 사용자는 자동으로 관리자가 된다.
// 그 외에는 일반 사용자로 가입하며, 이후 관리자 대시보드에서 권한을 조정할 수 있다.
function insertUser(PDO $db, string $username, string $passwordHash, ?string $walletAddress = null): array {
    $isFirstUser = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0;
    $role = $isFirstUser ? 'admin' : 'user';

    $stmt = $db->prepare("INSERT INTO users (username, password, wallet_address, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $passwordHash, $walletAddress, $role]);

    return ['id' => (int)$db->lastInsertId(), 'role' => $role];
}

function isAdminUser(PDO $db, int $userId): bool {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() === 'admin';
}

function seedMedia(PDO $db): void {
    $items = [
        ["id"=>"1","type"=>"image","name"=>"산 위의 일출","src"=>"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=480&q=70","date"=>"2024-03-15","size"=>"3.2 MB","duration"=>null,"tags"=>["자연","풍경"],"favorite"=>false],
        ["id"=>"2","type"=>"image","name"=>"도시의 밤","src"=>"https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?w=480&q=70","date"=>"2024-03-20","size"=>"4.1 MB","duration"=>null,"tags"=>["도시","야경"],"favorite"=>true],
        ["id"=>"3","type"=>"image","name"=>"파도치는 해변","src"=>"https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=480&q=70","date"=>"2024-04-02","size"=>"2.8 MB","duration"=>null,"tags"=>["바다","자연"],"favorite"=>false],
        ["id"=>"4","type"=>"image","name"=>"붉은 단풍숲","src"=>"https://images.unsplash.com/photo-1508739773434-c26b3d09e071?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1508739773434-c26b3d09e071?w=480&q=70","date"=>"2024-04-10","size"=>"3.5 MB","duration"=>null,"tags"=>["자연","가을"],"favorite"=>false],
        ["id"=>"5","type"=>"image","name"=>"눈 덮인 산장","src"=>"https://images.unsplash.com/photo-1518791841217-8f162f1912da?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1518791841217-8f162f1912da?w=480&q=70","date"=>"2024-01-08","size"=>"2.1 MB","duration"=>null,"tags"=>["겨울","풍경"],"favorite"=>true],
        ["id"=>"6","type"=>"image","name"=>"사막의 모래언덕","src"=>"https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=480&q=70","date"=>"2024-02-14","size"=>"3.9 MB","duration"=>null,"tags"=>["사막","자연"],"favorite"=>false],
        ["id"=>"7","type"=>"video","name"=>"바람에 흔들리는 들판","src"=>"https://www.w3schools.com/html/mov_bbb.mp4","thumb"=>null,"date"=>"2024-03-28","size"=>"14.3 MB","duration"=>"0:30","tags"=>["자연"],"favorite"=>false],
        ["id"=>"8","type"=>"video","name"=>"도시 타임랩스","src"=>"https://www.w3schools.com/html/mov_bbb.mp4","thumb"=>null,"date"=>"2024-04-05","size"=>"28.7 MB","duration"=>"1:15","tags"=>["도시","타임랩스"],"favorite"=>true],
        ["id"=>"9","type"=>"audio","name"=>"피아노 소나타","src"=>"https://www.w3schools.com/html/horse.mp3","thumb"=>null,"date"=>"2024-02-20","size"=>"5.4 MB","duration"=>"3:42","tags"=>["클래식","피아노"],"favorite"=>false],
        ["id"=>"10","type"=>"audio","name"=>"빗소리 ASMR","src"=>"https://www.w3schools.com/html/horse.mp3","thumb"=>null,"date"=>"2024-03-01","size"=>"8.2 MB","duration"=>"5:20","tags"=>["ASMR","자연"],"favorite"=>true],
        ["id"=>"11","type"=>"image","name"=>"열대우림 폭포","src"=>"https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=480&q=70","date"=>"2024-05-01","size"=>"4.6 MB","duration"=>null,"tags"=>["자연","폭포"],"favorite"=>false],
        ["id"=>"12","type"=>"image","name"=>"별이 빛나는 밤하늘","src"=>"https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=1200&q=80","thumb"=>"https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=480&q=70","date"=>"2024-05-10","size"=>"5.1 MB","duration"=>null,"tags"=>["우주","야경"],"favorite"=>true],
    ];

    $stmt = $db->prepare("
        INSERT INTO media (id, type, name, src, thumb, date, size, duration, tags, favorite)
        VALUES (:id, :type, :name, :src, :thumb, :date, :size, :duration, :tags, :favorite)
    ");

    foreach ($items as $item) {
        $stmt->execute([
            ':id'       => $item['id'],
            ':type'     => $item['type'],
            ':name'     => $item['name'],
            ':src'      => $item['src'],
            ':thumb'    => $item['thumb'],
            ':date'     => $item['date'],
            ':size'     => $item['size'],
            ':duration' => $item['duration'],
            ':tags'     => json_encode($item['tags'], JSON_UNESCAPED_UNICODE),
            ':favorite' => $item['favorite'] ? 1 : 0,
        ]);
    }
}
