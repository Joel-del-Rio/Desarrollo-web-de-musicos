<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Catálogo de memes</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=7">
  <style>
    .filter-bar { position: sticky; top: 0; z-index: 10; background: var(--bg); padding: .75rem 0; }

    .meme-play-btn {
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
      background: transparent; border: 2px solid var(--accent);
      color: var(--accent); cursor: pointer; transition: all .15s;
    }
    .meme-play-btn:hover { background: var(--accent); color: #fff; }

    .meme-row { display: flex; align-items: center; gap: .75rem; padding: .55rem .9rem; }
    .meme-row img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; flex-shrink: 0; }
    .meme-row-info { flex: 1; min-width: 0; }
    .meme-row-title { font-weight: 600; font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .meme-row-sub { font-size: .74rem; color: var(--muted); }
  </style>
</head>
<body>

<div class="container py-4" style="max-width:1040px">

  <div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="height:48px">
    <div>
      <h4 class="fw-black mb-0">Catálogo de memes</h4>
      <div class="small" style="color:var(--muted)">Consulta los memes disponibles</div>
    </div>
    <a href="<?= BASE_URL ?>/Vista/admin.php" class="btn btn-outline-secondary btn-sm rounded-pill ms-auto">‹ Volver al panel</a>
  </div>

  <!-- Filtros -->
  <div class="filter-bar">
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <input type="search" id="filter-text" class="form-control form-control-sm" style="max-width:220px"
             placeholder="Buscar título…" oninput="filterMemes()">
      <span class="small" id="count-label" style="color:var(--muted)"></span>
    </div>
  </div>

  <div id="memes-container">
    <div class="text-center py-5">
      <div class="game-spinner mx-auto"></div>
      <div class="mt-3 small" style="color:var(--muted)">Cargando memes…</div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';
let allMemes = [];

async function loadMemes() {
  const r = await fetch(`${API}?action=get_memes`);
  allMemes = await r.json();
  renderMemes(allMemes);
}

function filterMemes() {
  const txt = document.getElementById('filter-text').value.toLowerCase();
  const filtered = allMemes.filter(m => (m.title || '').toLowerCase().includes(txt));
  renderMemes(filtered);
}

function renderMemes(memes) {
  document.getElementById('count-label').textContent = `${memes.length} memes`;
  const c = document.getElementById('memes-container');
  if (!memes.length) { c.innerHTML = '<div class="text-center py-4 text-secondary">Sin resultados</div>'; return; }

  const sorted = [...memes].sort((a, b) => a.year - b.year || a.id - b.id);
  const groupCount = Math.min(3, sorted.length);
  const groups = Array.from({ length: groupCount }, () => []);
  sorted.forEach((m, i) => groups[Math.floor(i * groupCount / sorted.length)].push(m));

  let html = '';
  groups.forEach(list => {
    if (!list.length) return;
    const minY = list[0].year;
    const maxY = list[list.length - 1].year;
    const label = minY === maxY ? String(minY) : `${minY}–${maxY}`;
    html += `<h6 class="fw-bold mt-4 mb-2 text-uppercase small" style="color:var(--accent)">${esc(label)}</h6>
    <div class="card mb-3 p-0 overflow-hidden">`;
    list.forEach(m => {
      html += `
      <div class="meme-row" id="meme-row-${m.id}">
        <img src="https://img.youtube.com/vi/${m.youtube_id}/default.jpg" alt="Miniatura del vídeo">
        <div class="meme-row-info">
          <div class="meme-row-title">${esc(m.title || '(sin título)')}</div>
          <div class="meme-row-sub">${m.year}</div>
        </div>
        <button class="meme-play-btn" onclick="togglePlayMeme(${m.id}, '${m.youtube_id}', ${m.start_seconds || 0})" title="Reproducir">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><polygon points="1,0 12,6 1,12"/></svg>
        </button>
      </div>
      <div id="meme-player-${m.id}" class="d-none p-2"></div>`;
    });
    html += `</div>`;
  });
  c.innerHTML = html;
}

function togglePlayMeme(id, youtubeId, startSeconds) {
  const box = document.getElementById(`meme-player-${id}`);
  if (!box.classList.contains('d-none')) {
    box.classList.add('d-none');
    box.innerHTML = '';
    return;
  }
  document.querySelectorAll('[id^="meme-player-"]').forEach(el => { el.classList.add('d-none'); el.innerHTML = ''; });
  const url = `https://www.youtube.com/embed/${youtubeId}?autoplay=1&start=${startSeconds || 0}&playsinline=1`;
  box.innerHTML = `<iframe src="${url}" style="width:100%;max-width:360px;aspect-ratio:16/9;border:0;border-radius:8px" allow="autoplay" allowfullscreen></iframe>`;
  box.classList.remove('d-none');
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

loadMemes();
</script>
</body>
</html>
