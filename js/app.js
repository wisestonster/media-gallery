/* ===== State ===== */
const state = {
  items: [],
  filter: "all",
  query: "",
  sort: "date-desc",
  view: "grid",
  lightboxIndex: -1,
  filteredItems: [],
};

/* ===== DOM refs ===== */
const gallery         = document.getElementById("gallery");
const emptyState      = document.getElementById("emptyState");
const visibleCount    = document.getElementById("visibleCount");
const filterBtns      = document.querySelectorAll(".filter-btn");
const sortSelect      = document.getElementById("sortSelect");
const viewToggle      = document.getElementById("viewToggle");
const viewIcon        = document.getElementById("viewIcon");
const searchToggle    = document.getElementById("searchToggle");
const searchBar       = document.getElementById("searchBar");
const searchInput     = document.getElementById("searchInput");

const lightbox        = document.getElementById("lightbox");
const lightboxMedia   = document.getElementById("lightboxMedia");
const lightboxTitle   = document.getElementById("lightboxTitle");
const lightboxMeta    = document.getElementById("lightboxMeta");
const lightboxCounter = document.getElementById("lightboxCounter");
const lightboxClose   = document.getElementById("lightboxClose");
const lightboxPrev    = document.getElementById("lightboxPrev");
const lightboxNext    = document.getElementById("lightboxNext");
const lightboxFav     = document.getElementById("lightboxFav");
const lightboxEdit    = document.getElementById("lightboxEdit");
const lightboxDelete  = document.getElementById("lightboxDelete");
const lightboxBackdrop = document.getElementById("lightboxBackdrop");

const uploadModal    = document.getElementById("uploadModal");
const uploadBtn      = document.getElementById("uploadBtn");
const uploadClose    = document.getElementById("uploadClose");
const uploadCancel   = document.getElementById("uploadCancel");
const uploadConfirm  = document.getElementById("uploadConfirm");
const uploadBackdrop = document.getElementById("uploadBackdrop");
const dropZone       = document.getElementById("dropZone");
const fileInput      = document.getElementById("fileInput");
const uploadPreview  = document.getElementById("uploadPreview");
const uploadError    = document.getElementById("uploadError");

const editModal      = document.getElementById("editModal");
const editBackdrop   = document.getElementById("editBackdrop");
const editClose      = document.getElementById("editClose");
const editCancel     = document.getElementById("editCancel");
const editSave       = document.getElementById("editSave");
const editDeleteBtn  = document.getElementById("editDeleteBtn");
const editId         = document.getElementById("editId");
const editName       = document.getElementById("editName");
const editTags       = document.getElementById("editTags");
const editDate       = document.getElementById("editDate");

const confirmModal   = document.getElementById("confirmModal");
const confirmBackdrop = document.getElementById("confirmBackdrop");
const confirmMsg     = document.getElementById("confirmMsg");
const confirmCancel  = document.getElementById("confirmCancel");
const confirmOk      = document.getElementById("confirmOk");

/* ===== Helpers ===== */
function escapeHtml(str) {
  return String(str ?? "").replace(/[&<>"']/g, c => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

/* ===== Icons ===== */
const ICONS = {
  video: `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>`,
  audio: `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`,
  play:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>`,
  eye:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`,
  edit:  `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
  trash: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>`,
  heart_filled:  `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>`,
  heart_outline: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>`,
};

/* ===== Auth ===== */
async function apiFetch(url, options = {}) {
  const res = await fetch(url, options);
  if (res.status === 401) { location.href = "login.php"; return null; }
  return res;
}

document.getElementById("logoutBtn")?.addEventListener("click", async () => {
  await fetch("api/auth.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "logout" }),
  });
  location.href = "login.php";
});

/* ===== Render ===== */
function getFiltered() {
  let items = state.items.filter(item => {
    const matchType = state.filter === "all" || item.type === state.filter;
    const q = state.query.toLowerCase();
    const matchQuery = !q || item.name.toLowerCase().includes(q) || item.tags.some(t => t.toLowerCase().includes(q));
    return matchType && matchQuery;
  });
  items.sort((a, b) => {
    if (state.sort === "date-desc") return new Date(b.date) - new Date(a.date);
    if (state.sort === "date-asc")  return new Date(a.date) - new Date(b.date);
    if (state.sort === "name-asc")  return a.name.localeCompare(b.name, "ko");
    if (state.sort === "name-desc") return b.name.localeCompare(a.name, "ko");
    return 0;
  });
  return items;
}

function formatDate(str) {
  return new Date(str).toLocaleDateString("ko-KR", { year: "numeric", month: "short", day: "numeric" });
}

function buildThumb(item) {
  if (item.type === "image") return `<img src="${escapeHtml(item.thumb || item.src)}" alt="${escapeHtml(item.name)}" loading="lazy" />`;
  return `<div class="thumb-placeholder thumb-${item.type}">${ICONS[item.type] || ""}</div>`;
}

function buildItem(item, idx) {
  const el = document.createElement("div");
  el.className = "gallery-item";
  el.dataset.id = item.id;
  el.dataset.idx = idx;
  el.setAttribute("role", "listitem");
  el.setAttribute("tabindex", "0");
  el.setAttribute("aria-label", `${item.name} (${item.type})`);

  const overlayIcon = item.type === "image" ? ICONS.eye : ICONS.play;
  const favHtml     = item.favorite ? `<span class="fav-badge">${ICONS.heart_filled}</span>` : "";
  const metaExtra   = item.duration ? `<span>• ${item.duration}</span>` : "";

  el.innerHTML = `
    <div class="item-thumb">
      ${buildThumb(item)}
      <span class="type-badge badge-${item.type}">${item.type === "image" ? "이미지" : item.type === "video" ? "비디오" : "오디오"}</span>
      ${favHtml}
      <div class="item-overlay">
        <button class="overlay-btn" data-action="open" aria-label="열기">${overlayIcon}</button>
        <button class="overlay-btn overlay-sm" data-action="edit" aria-label="수정">${ICONS.edit}</button>
        <button class="overlay-btn overlay-sm danger" data-action="delete" aria-label="삭제">${ICONS.trash}</button>
      </div>
    </div>
    <div class="item-info">
      <div class="item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
      <div class="item-meta">
        <span>${formatDate(item.date)}</span>
        ${metaExtra}
        <span>${escapeHtml(item.size)}</span>
      </div>
    </div>
  `;

  el.addEventListener("click", e => {
    if (e.target.closest("[data-action='edit']"))   {
      if (!window.IS_LOGGED_IN) { location.href = "login.php"; return; }
      openEditModal(item); return;
    }
    if (e.target.closest("[data-action='delete']")) {
      if (!window.IS_LOGGED_IN) { location.href = "login.php"; return; }
      confirmDelete(item); return;
    }
    openLightbox(idx);
  });
  el.addEventListener("keydown", e => {
    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openLightbox(idx); }
  });
  return el;
}

function render() {
  const items = getFiltered();
  state.filteredItems = items;
  gallery.innerHTML = "";

  if (items.length === 0) {
    emptyState.hidden = false;
    visibleCount.textContent = "0";
    return;
  }
  emptyState.hidden = true;
  visibleCount.textContent = items.length;

  const frag = document.createDocumentFragment();
  items.forEach((item, idx) => frag.appendChild(buildItem(item, idx)));
  gallery.appendChild(frag);
}

/* ===== Filters / Sort / View ===== */
filterBtns.forEach(btn => {
  btn.addEventListener("click", () => {
    filterBtns.forEach(b => { b.classList.remove("active"); b.setAttribute("aria-selected", "false"); });
    btn.classList.add("active");
    btn.setAttribute("aria-selected", "true");
    state.filter = btn.dataset.filter;
    render();
  });
});

sortSelect.addEventListener("change", () => { state.sort = sortSelect.value; render(); });

const LIST_ICON = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>`;
const GRID_ICON = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>`;

viewToggle.addEventListener("click", () => {
  if (state.view === "grid") {
    state.view = "list";
    gallery.classList.replace("grid-view", "list-view");
    viewIcon.outerHTML = LIST_ICON;
    viewToggle.title = "그리드 뷰";
  } else {
    state.view = "grid";
    gallery.classList.replace("list-view", "grid-view");
    viewToggle.title = "리스트 뷰";
  }
  render();
});

/* ===== Search ===== */
searchToggle.addEventListener("click", () => {
  const hidden = searchBar.hidden;
  searchBar.hidden = !hidden;
  if (!hidden) { searchInput.value = ""; state.query = ""; render(); }
  else setTimeout(() => searchInput.focus(), 50);
});

searchInput.addEventListener("input", () => { state.query = searchInput.value.trim(); render(); });

document.addEventListener("keydown", e => {
  if (e.key === "Escape" && !searchBar.hidden) {
    searchBar.hidden = true; searchInput.value = ""; state.query = ""; render();
  }
});

/* ===== Lightbox ===== */
function stopMedia() {
  lightboxMedia.querySelectorAll("video, audio").forEach(el => { el.pause(); el.currentTime = 0; });
}

function openLightbox(idx) {
  state.lightboxIndex = idx;
  renderLightbox();
  lightbox.hidden = false;
  document.body.style.overflow = "hidden";
  lightboxClose.focus();
}

function closeLightbox() {
  stopMedia();
  lightbox.hidden = true;
  document.body.style.overflow = "";
}

function renderLightbox() {
  const item = state.filteredItems[state.lightboxIndex];
  if (!item) return;
  stopMedia();

  if (item.type === "image") {
    lightboxMedia.innerHTML = `<img src="${escapeHtml(item.src)}" alt="${escapeHtml(item.name)}" />`;
  } else if (item.type === "video") {
    lightboxMedia.innerHTML = `<video src="${escapeHtml(item.src)}" controls autoplay muted></video>`;
  } else {
    lightboxMedia.innerHTML = `
      <div class="lightbox-audio-wrap">
        <div class="lightbox-audio-icon">${ICONS.audio}</div>
        <audio src="${escapeHtml(item.src)}" controls autoplay></audio>
      </div>`;
  }

  lightboxTitle.textContent = item.name;
  lightboxMeta.textContent = `${item.type.toUpperCase()} · ${item.size} · ${formatDate(item.date)}${item.tags.length ? " · " + item.tags.join(", ") : ""}`;
  lightboxCounter.textContent = `${state.lightboxIndex + 1} / ${state.filteredItems.length}`;

  lightboxFav.innerHTML = item.favorite ? ICONS.heart_filled : ICONS.heart_outline;
  lightboxFav.classList.toggle("active", item.favorite);
  lightboxFav.setAttribute("aria-label", item.favorite ? "즐겨찾기 해제" : "즐겨찾기");

  lightboxPrev.style.visibility = state.lightboxIndex > 0 ? "visible" : "hidden";
  lightboxNext.style.visibility = state.lightboxIndex < state.filteredItems.length - 1 ? "visible" : "hidden";
}

lightboxClose.addEventListener("click", closeLightbox);
lightboxBackdrop.addEventListener("click", closeLightbox);
lightboxPrev.addEventListener("click", () => { if (state.lightboxIndex > 0) { state.lightboxIndex--; renderLightbox(); } });
lightboxNext.addEventListener("click", () => { if (state.lightboxIndex < state.filteredItems.length - 1) { state.lightboxIndex++; renderLightbox(); } });

lightboxFav.addEventListener("click", async () => {
  const item = state.filteredItems[state.lightboxIndex];
  if (!item) return;
  const src = state.items.find(i => String(i.id) === String(item.id));
  if (src) { src.favorite = !src.favorite; item.favorite = src.favorite; }
  renderLightbox();

  const galleryItem = gallery.querySelector(`[data-id="${item.id}"]`);
  if (galleryItem) {
    const favBadge = galleryItem.querySelector(".fav-badge");
    if (item.favorite && !favBadge) {
      const span = document.createElement("span");
      span.className = "fav-badge"; span.innerHTML = ICONS.heart_filled;
      galleryItem.querySelector(".item-thumb").appendChild(span);
    } else if (!item.favorite && favBadge) {
      favBadge.remove();
    }
  }

  await apiFetch("api/media.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id: item.id, favorite: item.favorite }),
  });
});

lightboxEdit.addEventListener("click", () => {
  const item = state.filteredItems[state.lightboxIndex];
  if (item) openEditModal(item);
});

lightboxDelete.addEventListener("click", () => {
  const item = state.filteredItems[state.lightboxIndex];
  if (item) confirmDelete(item);
});

document.addEventListener("keydown", e => {
  if (lightbox.hidden) return;
  if (e.key === "Escape")     closeLightbox();
  if (e.key === "ArrowLeft")  lightboxPrev.click();
  if (e.key === "ArrowRight") lightboxNext.click();
});

/* ===== Edit Modal ===== */
function openEditModal(item) {
  editId.value    = item.id;
  editName.value  = item.name;
  editTags.value  = item.tags.join(", ");
  editDate.value  = item.date;
  editModal.hidden = false;
  setTimeout(() => editName.focus(), 50);
}

function closeEditModal() { editModal.hidden = true; }

editClose.addEventListener("click", closeEditModal);
editCancel.addEventListener("click", closeEditModal);
editBackdrop.addEventListener("click", closeEditModal);

editSave.addEventListener("click", async () => {
  const id   = editId.value;
  const name = editName.value.trim();
  const tags = editTags.value.split(",").map(t => t.trim()).filter(Boolean);
  const date = editDate.value;
  if (!name) { editName.focus(); return; }

  editSave.disabled = true;
  const res = await apiFetch("api/media.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id, name, tags, date }),
  });
  editSave.disabled = false;
  if (!res || !res.ok) return;

  const updated = await res.json();
  const idx = state.items.findIndex(i => String(i.id) === String(id));
  if (idx !== -1) state.items[idx] = updated;

  closeEditModal();
  render();

  if (!lightbox.hidden) {
    const fi = state.filteredItems.findIndex(i => String(i.id) === String(id));
    if (fi !== -1) { state.filteredItems[fi] = updated; state.lightboxIndex = fi; renderLightbox(); }
  }
});

editDeleteBtn.addEventListener("click", () => {
  const id   = editId.value;
  const item = state.items.find(i => String(i.id) === String(id));
  closeEditModal();
  if (item) confirmDelete(item);
});

/* ===== Confirm / Delete ===== */
let _confirmCallback = null;

function showConfirm(msg, onOk) {
  confirmMsg.textContent = msg;
  _confirmCallback = onOk;
  confirmModal.hidden = false;
}

function closeConfirm() { confirmModal.hidden = true; _confirmCallback = null; }

confirmCancel.addEventListener("click", closeConfirm);
confirmBackdrop.addEventListener("click", closeConfirm);
confirmOk.addEventListener("click", async () => {
  confirmOk.disabled = true;
  if (_confirmCallback) await _confirmCallback();
  confirmOk.disabled = false;
  closeConfirm();
});

async function confirmDelete(item) {
  showConfirm(`"${item.name}"을(를) 삭제하시겠습니까?`, async () => {
    const res = await apiFetch(`api/media.php?id=${encodeURIComponent(item.id)}`, { method: "DELETE" });
    if (!res || !res.ok) return;

    if (!lightbox.hidden && state.filteredItems[state.lightboxIndex]?.id === item.id) {
      closeLightbox();
    }
    state.items = state.items.filter(i => String(i.id) !== String(item.id));
    render();
  });
}

/* ===== Upload Modal ===== */
let pendingFiles = [];

uploadBtn?.addEventListener("click", () => { uploadModal.hidden = false; });
uploadClose.addEventListener("click", closeUpload);
uploadCancel.addEventListener("click", closeUpload);
uploadBackdrop.addEventListener("click", closeUpload);

function closeUpload() {
  uploadModal.hidden = true;
  pendingFiles = [];
  uploadPreview.innerHTML = "";
  uploadError.hidden = true;
  fileInput.value = "";
  uploadConfirm.disabled = true;
}

function showUploadError(msg) {
  uploadError.textContent = msg;
  uploadError.hidden = false;
}

function getFileIcon(type) {
  if (type.startsWith("image/")) return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
  if (type.startsWith("video/")) return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>`;
  return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`;
}

function handleFiles(files) {
  pendingFiles = [...files];
  uploadPreview.innerHTML = "";
  if (pendingFiles.length === 0) { uploadConfirm.disabled = true; return; }
  pendingFiles.forEach(f => {
    const chip = document.createElement("div");
    chip.className = "preview-chip";
    chip.innerHTML = `${getFileIcon(f.type)}<span title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</span>`;
    uploadPreview.appendChild(chip);
  });
  uploadConfirm.disabled = false;
}

fileInput.addEventListener("change", () => handleFiles(fileInput.files));
dropZone.addEventListener("dragover", e => { e.preventDefault(); dropZone.classList.add("drag-over"); });
dropZone.addEventListener("dragleave", () => dropZone.classList.remove("drag-over"));
dropZone.addEventListener("drop", e => { e.preventDefault(); dropZone.classList.remove("drag-over"); handleFiles(e.dataTransfer.files); });

uploadConfirm.addEventListener("click", async () => {
  uploadConfirm.disabled = true;
  uploadError.hidden = true;
  const errors = [];

  for (const file of pendingFiles) {
    const fd = new FormData();
    fd.append("file", file);
    const uploadRes = await apiFetch("api/upload.php", { method: "POST", body: fd });
    if (!uploadRes) return;
    if (!uploadRes.ok) {
      const data = await uploadRes.json().catch(() => ({}));
      errors.push(`${file.name}: ${data.error ?? "업로드 실패"}`);
      continue;
    }
    const { url } = await uploadRes.json();

    const type = file.type.startsWith("image/") ? "image" : file.type.startsWith("video/") ? "video" : "audio";
    const newItem = {
      type, name: file.name.replace(/\.[^.]+$/, ""), src: url,
      thumb: type === "image" ? url : null,
      date: new Date().toISOString().split("T")[0],
      size: `${(file.size / 1024 / 1024).toFixed(1)} MB`,
      tags: [], favorite: false,
    };
    const addRes = await apiFetch("api/media.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(newItem),
    });
    if (!addRes || !addRes.ok) continue;
    state.items.unshift(await addRes.json());
  }

  if (errors.length > 0) {
    showUploadError(errors.join("\n"));
    uploadConfirm.disabled = false;
    render();
  } else {
    closeUpload();
    render();
  }
});

/* ===== Guest mode ===== */
if (!window.IS_LOGGED_IN) {
  [lightboxFav, lightboxEdit, lightboxDelete].forEach(btn => {
    btn.addEventListener("click", e => { e.stopImmediatePropagation(); location.href = "login.php"; }, true);
  });
}

/* ===== Init ===== */
async function init() {
  try {
    const res = await apiFetch("api/media.php");
    if (!res) return;
    state.items = await res.json();
  } catch (e) {
    console.error("미디어 로드 실패:", e);
    state.items = [];
  }
  render();
}

init();
