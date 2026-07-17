/* ===== Helpers ===== */
function escapeHtml(str) {
  return String(str ?? "").replace(/[&<>"']/g, c => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

function formatDate(str) {
  return new Date(str).toLocaleDateString("ko-KR", { year: "numeric", month: "short", day: "numeric" });
}

function formatBytes(bytes) {
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + " " + units[i];
}

const TYPE_LABEL = { image: "이미지", video: "비디오", audio: "오디오" };

async function apiFetch(url, options = {}) {
  const res = await fetch(url, options);
  if (res.status === 401) { location.href = "login.php"; return null; }
  if (res.status === 403) { location.href = "index.php"; return null; }
  return res;
}

/* ===== Logout ===== */
document.getElementById("logoutBtn")?.addEventListener("click", async () => {
  await fetch("api/auth.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "logout" }),
  });
  location.href = "login.php";
});

/* ===== Confirm modal ===== */
const confirmModal    = document.getElementById("confirmModal");
const confirmBackdrop = document.getElementById("confirmBackdrop");
const confirmMsg      = document.getElementById("confirmMsg");
const confirmCancel   = document.getElementById("confirmCancel");
const confirmOk       = document.getElementById("confirmOk");
let pendingAction = null;

function askConfirm(message, action) {
  confirmMsg.textContent = message;
  pendingAction = action;
  confirmModal.hidden = false;
}
function closeConfirm() {
  confirmModal.hidden = true;
  pendingAction = null;
}
confirmCancel.addEventListener("click", closeConfirm);
confirmBackdrop.addEventListener("click", closeConfirm);
confirmOk.addEventListener("click", async () => {
  if (pendingAction) await pendingAction();
  closeConfirm();
});

/* ===== Tabs ===== */
const tabs         = document.querySelectorAll(".admin-tab");
const usersPanel    = document.getElementById("usersPanel");
const commentsPanel = document.getElementById("commentsPanel");

tabs.forEach(tab => {
  tab.addEventListener("click", () => {
    tabs.forEach(t => {
      t.classList.toggle("active", t === tab);
      t.setAttribute("aria-selected", t === tab ? "true" : "false");
    });
    const isUsers = tab.dataset.tab === "users";
    usersPanel.hidden    = !isUsers;
    commentsPanel.hidden = isUsers;
  });
});

/* ===== Stats ===== */
async function loadStats() {
  const res = await apiFetch("api/admin.php?resource=stats");
  if (!res || !res.ok) return;
  const s = await res.json();

  const typeBreakdown = Object.entries(s.media_by_type)
    .map(([type, count]) => `${TYPE_LABEL[type] || type} ${count}`)
    .join(" · ") || "없음";

  const cards = [
    { label: "전체 사용자", value: s.users, sub: `관리자 ${s.admins}명` },
    { label: "전체 미디어", value: s.media, sub: typeBreakdown },
    { label: "프로젝트", value: s.projects, sub: "" },
    { label: "댓글", value: s.comments, sub: "" },
    { label: "저장 용량", value: formatBytes(s.storage_bytes), sub: "uploads/" },
  ];

  document.getElementById("statGrid").innerHTML = cards.map(c => `
    <div class="stat-card">
      <span class="stat-label">${escapeHtml(c.label)}</span>
      <span class="stat-value">${escapeHtml(String(c.value))}</span>
      ${c.sub ? `<span class="stat-sub">${escapeHtml(c.sub)}</span>` : ""}
    </div>
  `).join("");
}

/* ===== Users ===== */
async function loadUsers() {
  const res = await apiFetch("api/admin.php?resource=users");
  if (!res || !res.ok) return;
  const { current_user_id, users } = await res.json();

  const body = document.getElementById("usersBody");
  document.getElementById("usersEmpty").hidden = users.length > 0;

  body.innerHTML = users.map(u => {
    const isSelf = u.id === current_user_id;
    const roleBadge = u.role === "admin"
      ? `<span class="role-badge admin">관리자</span>`
      : `<span class="role-badge">준회원</span>`;
    const toggleLabel = u.role === "admin" ? "권한 해제" : "관리자로 지정";

    return `
      <tr>
        <td>${escapeHtml(u.username)}${isSelf ? ' <span class="me-tag">(나)</span>' : ""}</td>
        <td>${roleBadge}</td>
        <td>${u.has_wallet ? "연동됨" : "-"}</td>
        <td>${u.comment_count}</td>
        <td>${formatDate(u.created_at)}</td>
        <td class="actions-col">
          <div class="row-actions">
            <button class="btn secondary sm" data-action="toggle-role" data-id="${u.id}" data-role="${u.role}" ${isSelf ? "disabled" : ""}>${toggleLabel}</button>
            <button class="btn danger sm" data-action="delete-user" data-id="${u.id}" data-username="${escapeHtml(u.username)}" ${isSelf ? "disabled" : ""}>삭제</button>
          </div>
        </td>
      </tr>
    `;
  }).join("");
}

document.getElementById("usersBody").addEventListener("click", e => {
  const btn = e.target.closest("button[data-action]");
  if (!btn) return;
  const id = Number(btn.dataset.id);

  if (btn.dataset.action === "toggle-role") {
    const nextRole = btn.dataset.role === "admin" ? "user" : "admin";
    const msg = nextRole === "admin" ? "이 사용자에게 관리자 권한을 부여할까요?" : "이 사용자의 관리자 권한을 해제할까요?";
    askConfirm(msg, async () => {
      const res = await apiFetch("api/admin.php?resource=users", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, role: nextRole }),
      });
      const data = res && await res.json();
      if (!res || !res.ok) { alert(data?.error || "권한 변경에 실패했습니다"); return; }
      loadUsers();
      loadStats();
    });
  }

  if (btn.dataset.action === "delete-user") {
    askConfirm(`'${btn.dataset.username}' 계정을 삭제할까요? 작성한 댓글도 함께 삭제됩니다.`, async () => {
      const res = await apiFetch(`api/admin.php?resource=users&id=${encodeURIComponent(id)}`, { method: "DELETE" });
      const data = res && await res.json();
      if (!res || !res.ok) { alert(data?.error || "삭제에 실패했습니다"); return; }
      loadUsers();
      loadStats();
    });
  }
});

/* ===== Comments ===== */
async function loadComments() {
  const res = await apiFetch("api/admin.php?resource=comments");
  if (!res || !res.ok) return;
  const comments = await res.json();

  const body = document.getElementById("commentsBody");
  document.getElementById("commentsEmpty").hidden = comments.length > 0;

  body.innerHTML = comments.map(c => `
    <tr>
      <td>${escapeHtml(c.username)}</td>
      <td class="comment-content">${escapeHtml(c.content)}</td>
      <td>${escapeHtml(c.media_name || "(삭제된 미디어)")}</td>
      <td>${formatDate(c.created_at)}</td>
      <td class="actions-col">
        <button class="btn danger sm" data-action="delete-comment" data-id="${escapeHtml(c.id)}">삭제</button>
      </td>
    </tr>
  `).join("");
}

document.getElementById("commentsBody").addEventListener("click", e => {
  const btn = e.target.closest("button[data-action='delete-comment']");
  if (!btn) return;
  const id = btn.dataset.id;

  askConfirm("이 댓글을 삭제할까요?", async () => {
    const res = await apiFetch(`api/admin.php?resource=comments&id=${encodeURIComponent(id)}`, { method: "DELETE" });
    const data = res && await res.json();
    if (!res || !res.ok) { alert(data?.error || "삭제에 실패했습니다"); return; }
    loadComments();
    loadStats();
  });
});

/* ===== Init ===== */
loadStats();
loadUsers();
loadComments();
