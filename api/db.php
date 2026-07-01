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
            created_at TEXT    DEFAULT (datetime('now'))
        );
    ");

    // 최초 실행 시 샘플 데이터 삽입
    $count = $db->query("SELECT COUNT(*) FROM media")->fetchColumn();
    if ($count == 0) {
        seedMedia($db);
    }

    return $db;
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
