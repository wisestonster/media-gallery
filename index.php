<?php
session_start();
$isLoggedIn = !empty($_SESSION['user_id']);
$username   = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>미디어 갤러리</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>

  <!-- Header -->
  <header class="header">
    <div class="header-inner">
      <div class="logo">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect x="2" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
          <rect x="16" y="2" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
          <rect x="2" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.6"/>
          <rect x="16" y="16" width="10" height="10" rx="2" fill="currentColor" opacity="0.9"/>
        </svg>
        <span>미디어 갤러리</span>
      </div>
      <nav class="nav-filters" role="tablist" aria-label="미디어 필터">
        <button class="filter-btn active" data-filter="all" role="tab" aria-selected="true">전체</button>
        <button class="filter-btn" data-filter="image" role="tab" aria-selected="false">이미지</button>
        <button class="filter-btn" data-filter="video" role="tab" aria-selected="false">비디오</button>
        <button class="filter-btn" data-filter="audio" role="tab" aria-selected="false">오디오</button>
      </nav>
      <div class="header-actions">
        <?php if ($isLoggedIn): ?>
        <div class="user-info">
          <span class="username-badge"><?= $username ?></span>
          <button class="icon-btn" id="logoutBtn" aria-label="로그아웃" title="로그아웃">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
        <?php else: ?>
        <a href="login.php" class="btn secondary" style="font-size:0.85rem;padding:0.35rem 0.9rem;">로그인</a>
        <?php endif; ?>
        <button class="icon-btn" id="searchToggle" aria-label="검색" title="검색">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
        </button>
        <button class="icon-btn" id="viewToggle" aria-label="뷰 전환" title="뷰 전환">
          <svg id="viewIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
          </svg>
        </button>
        <?php if ($isLoggedIn): ?>
        <button class="icon-btn" id="uploadBtn" aria-label="업로드" title="미디어 추가">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <!-- Search bar -->
    <div class="search-bar" id="searchBar" hidden>
      <div class="search-inner">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" id="searchInput" placeholder="미디어 검색..." aria-label="미디어 검색" autocomplete="off" />
        <kbd>Esc</kbd>
      </div>
    </div>
  </header>

  <!-- Stats bar -->
  <div class="stats-bar">
    <span id="statsText">전체 <strong id="visibleCount">0</strong>개</span>
    <div class="sort-select">
      <label for="sortSelect" class="sr-only">정렬</label>
      <select id="sortSelect">
        <option value="date-desc">최신순</option>
        <option value="date-asc">오래된순</option>
        <option value="name-asc">이름순 ↑</option>
        <option value="name-desc">이름순 ↓</option>
      </select>
    </div>
  </div>

  <!-- Gallery -->
  <main class="gallery-wrapper">
    <div class="gallery grid-view" id="gallery" role="list" aria-label="미디어 갤러리">
      <!-- Items injected by JS -->
    </div>
    <div class="empty-state" id="emptyState" hidden>
      <svg width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" opacity="0.2"/>
        <path d="M22 42 L32 24 L42 42 Z" stroke="currentColor" stroke-width="2" opacity="0.3" fill="none"/>
        <circle cx="40" cy="26" r="4" stroke="currentColor" stroke-width="2" opacity="0.3"/>
      </svg>
      <p>검색 결과가 없습니다</p>
    </div>
  </main>

  <!-- Lightbox -->
  <div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="미디어 뷰어">
    <div class="lightbox-backdrop" id="lightboxBackdrop"></div>
    <div class="lightbox-container">
      <button class="lightbox-close" id="lightboxClose" aria-label="닫기">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </button>
      <button class="lightbox-nav prev" id="lightboxPrev" aria-label="이전">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>
      <div class="lightbox-media" id="lightboxMedia"></div>
      <button class="lightbox-nav next" id="lightboxNext" aria-label="다음">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
      <div class="lightbox-info">
        <div>
          <h2 class="lightbox-title" id="lightboxTitle"></h2>
          <p class="lightbox-meta" id="lightboxMeta"></p>
        </div>
        <div class="lightbox-actions">
          <button class="lightbox-action-btn" id="lightboxFav" aria-label="즐겨찾기">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </button>
          <button class="lightbox-action-btn" id="lightboxEdit" aria-label="수정">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
          <button class="lightbox-action-btn danger" id="lightboxDelete" aria-label="삭제">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
          </button>
          <button class="lightbox-action-btn" id="lightboxDownload" aria-label="다운로드">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>
  </div>

  <!-- Upload Modal -->
  <div class="modal" id="uploadModal" hidden role="dialog" aria-modal="true" aria-label="미디어 업로드">
    <div class="modal-backdrop" id="uploadBackdrop"></div>
    <div class="modal-box">
      <div class="modal-header">
        <h2>미디어 추가</h2>
        <button class="icon-btn" id="uploadClose" aria-label="닫기">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="drop-zone" id="dropZone">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <p>파일을 드래그하거나 클릭하여 업로드</p>
        <span>이미지, 비디오, 오디오 지원</span>
        <input type="file" id="fileInput" multiple accept="image/*,video/*,audio/*" aria-label="파일 선택" />
      </div>
      <div class="upload-preview" id="uploadPreview"></div>
      <div class="upload-error" id="uploadError" hidden></div>
      <div class="modal-footer">
        <button class="btn secondary" id="uploadCancel">취소</button>
        <button class="btn primary" id="uploadConfirm" disabled>갤러리에 추가</button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal" id="editModal" hidden role="dialog" aria-modal="true" aria-label="미디어 수정">
    <div class="modal-backdrop" id="editBackdrop"></div>
    <div class="modal-box">
      <div class="modal-header">
        <h2>미디어 수정</h2>
        <button class="icon-btn" id="editClose" aria-label="닫기">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="edit-form">
        <input type="hidden" id="editId" />
        <div class="form-field">
          <label for="editName">이름</label>
          <input type="text" id="editName" placeholder="미디어 이름" />
        </div>
        <div class="form-field">
          <label for="editTags">태그 <span class="field-hint">쉼표로 구분</span></label>
          <input type="text" id="editTags" placeholder="예: 자연, 풍경" />
        </div>
        <div class="form-field">
          <label for="editDate">날짜</label>
          <input type="date" id="editDate" />
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn danger" id="editDeleteBtn">삭제</button>
        <div style="flex:1"></div>
        <button class="btn secondary" id="editCancel">취소</button>
        <button class="btn primary" id="editSave">저장</button>
      </div>
    </div>
  </div>

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

  <script>window.IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;</script>
  <script src="js/app.js"></script>
</body>
</html>
