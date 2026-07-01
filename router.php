<?php
// php -S 용 라우터: 문서 루트에 함께 들어있는 민감 파일/디렉터리에 대한
// 직접 접근을 차단한다. (data/gallery.db는 비밀번호 해시가 담긴 SQLite DB이고,
// uploads/ 아래에서 .php가 실행되면 업로드 취약점과 결합해 원격 코드 실행으로 이어질 수 있음)

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/data(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if (preg_match('#^/uploads/.*\.(php|phtml|php\d?|phar)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

return false; // 나머지는 내장 서버의 기본 동작(정적 파일 서빙 / PHP 실행)에 맡긴다
