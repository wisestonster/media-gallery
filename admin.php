<?php
require_once __DIR__ . '/api/session.php';
startSecureSession();
require_once __DIR__ . '/api/db.php';
$db = getDb();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!$me || $me['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$username = htmlspecialchars($me['username']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>관리자 대시보드 — 미디어 갤러리</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/admin.css" />
</head>
<body>

  <header class="header">
    <div class="header-inner">
      <div class="logo">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect x="2" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
          <rect x="16" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
          <rect x="2" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
          <rect x="16" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
        </svg>
        <span>관리자 대시보드</span>
      </div>
      <div style="flex:1"></div>
      <div class="header-actions">
        <a href="index.php" class="btn secondary" style="font-size:0.85rem;padding:0.35rem 0.9rem;">갤러리로 이동</a>
        <div class="user-info">
          <span class="username-badge"><?= $username ?></span>
          <button class="icon-btn" id="logoutBtn" aria-label="로그아웃" title="로그아웃">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="admin-main">
    <section class="stat-grid" id="statGrid" aria-label="통계"></section>

    <div class="admin-tabs" role="tablist">
      <button class="admin-tab active" data-tab="users" role="tab" aria-selected="true">사용자 관리</button>
      <button class="admin-tab" data-tab="comments" role="tab" aria-selected="false">댓글 관리</button>
    </div>

    <section class="admin-panel" id="usersPanel">
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>아이디</th>
              <th>권한</th>
              <th>지갑연동</th>
              <th>댓글수</th>
              <th>가입일</th>
              <th class="actions-col">작업</th>
            </tr>
          </thead>
          <tbody id="usersBody"></tbody>
        </table>
      </div>
      <p class="admin-empty" id="usersEmpty" hidden>사용자가 없습니다</p>
    </section>

    <section class="admin-panel" id="commentsPanel" hidden>
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>작성자</th>
              <th>내용</th>
              <th>미디어</th>
              <th>작성일</th>
              <th class="actions-col">작업</th>
            </tr>
          </thead>
          <tbody id="commentsBody"></tbody>
        </table>
      </div>
      <p class="admin-empty" id="commentsEmpty" hidden>댓글이 없습니다</p>
    </section>
  </main>

  <!-- Confirm Modal -->
  <div class="modal" id="confirmModal" hidden role="dialog" aria-modal="true">
    <div class="modal-backdrop" id="confirmBackdrop"></div>
    <div class="modal-box confirm-box">
      <div class="confirm-content">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <p id="confirmMsg"></p>
      </div>
      <div class="modal-footer">
        <button class="btn secondary" id="confirmCancel">취소</button>
        <button class="btn danger" id="confirmOk">삭제</button>
      </div>
    </div>
  </div>

  <script src="js/admin.js"></script>
</body>
</html>
