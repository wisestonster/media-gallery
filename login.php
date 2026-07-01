<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>미디어 갤러리 — 로그인</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="auth-page">

  <div class="auth-card">
    <div class="auth-logo">
      <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <rect x="2" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
        <rect x="16" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
        <rect x="2" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
        <rect x="16" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
      </svg>
      <span>미디어 갤러리</span>
    </div>

    <div class="auth-tabs" role="tablist">
      <button class="auth-tab active" data-tab="login" role="tab" aria-selected="true">로그인</button>
      <button class="auth-tab" data-tab="register" role="tab" aria-selected="false">회원가입</button>
    </div>

    <form id="authForm" novalidate>
      <div class="form-field">
        <label for="username">아이디</label>
        <input type="text" id="username" placeholder="아이디 입력" autocomplete="username" />
      </div>
      <div class="form-field">
        <label for="password">비밀번호</label>
        <input type="password" id="password" placeholder="비밀번호 입력" autocomplete="current-password" />
      </div>
      <div class="form-field" id="confirmField" hidden>
        <label for="confirm">비밀번호 확인</label>
        <input type="password" id="confirm" placeholder="비밀번호 재입력" autocomplete="new-password" />
      </div>

      <div class="auth-error" id="authError" hidden></div>

      <button type="submit" class="btn primary auth-submit" id="authSubmit">로그인</button>
    </form>
  </div>

  <script>
    const tabs        = document.querySelectorAll('.auth-tab');
    const confirmField = document.getElementById('confirmField');
    const authSubmit  = document.getElementById('authSubmit');
    const authError   = document.getElementById('authError');
    let mode = 'login';

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        mode = tab.dataset.tab;
        tabs.forEach(t => {
          t.classList.toggle('active', t === tab);
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        confirmField.hidden = mode !== 'register';
        authSubmit.textContent = mode === 'login' ? '로그인' : '회원가입';
        authError.hidden = true;
        document.getElementById('password').autocomplete =
          mode === 'login' ? 'current-password' : 'new-password';
      });
    });

    function showError(msg) {
      authError.textContent = msg;
      authError.hidden = false;
    }

    document.getElementById('authForm').addEventListener('submit', async e => {
      e.preventDefault();
      authError.hidden = true;

      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const confirm  = document.getElementById('confirm').value;

      if (!username || !password) { showError('아이디와 비밀번호를 입력해 주세요'); return; }
      if (mode === 'register' && password !== confirm) { showError('비밀번호가 일치하지 않습니다'); return; }

      authSubmit.disabled = true;
      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: mode, username, password }),
        });
        const data = await res.json();
        if (!res.ok) { showError(data.error); return; }
        location.href = 'index.php';
      } catch {
        showError('서버 오류가 발생했습니다');
      } finally {
        authSubmit.disabled = false;
      }
    });
  </script>
</body>
</html>
