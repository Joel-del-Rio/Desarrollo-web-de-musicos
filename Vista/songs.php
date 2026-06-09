<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Catálogo de canciones</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <style>
    .song-row td { vertical-align: middle; }
    .url-input   { font-size: .8rem; background: var(--bg2) !important; border-color: var(--bs-border-color) !important; color: var(--text) !important; }
    .url-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 .15rem rgba(233,69,96,.2) !important; }
    .save-btn    { font-size: .75rem; white-space: nowrap; }
    .badge-genre { font-size: .7rem; font-weight: 600; background: rgba(233,69,96,.15); color: var(--accent); border: 1px solid rgba(233,69,96,.3); }
    .saved-ok    { color: var(--success); font-size: .75rem; }
    .filter-bar  { position: sticky; top: 0; z-index: 10; background: var(--bg); padding: .75rem 0; }
  </style>
</head>
<body>

<div class="container py-4" style="max-width:900px">

  <div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= BASE_URL ?>/Imagenes/Logo.png" alt="Hitstoric" style="height:48px">
    <div>
      <h4 class="fw-black mb-0">Catálogo de canciones</h4>
      <div class="small" style="color:var(--muted)">Edita los enlaces de Spotify y YouTube por canción</div>
    </div>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm rounded-pill ms-auto">‹ Volver al panel</a>
  </div>

  <!-- Filtros -->
  <div class="filter-bar">
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <input type="search" id="filter-text" class="form-control form-control-sm" style="max-width:220px"
             placeholder="Buscar canción o artista…" oninput="filterSongs()">
      <select id="filter-genre" class="form-select form-select-sm" style="max-width:200px" onchange="filterSongs()">
        <option value="">Todos los géneros</option>
      </select>
      <span class="small" id="count-label" style="color:var(--muted)"></span>
      <button class="btn btn-game btn-sm rounded-pill fw-bold ms-auto px-4" onclick="saveAll(this)">
        💾 Guardar todo
      </button>
      <span id="save-all-ok" class="saved-ok d-none">✓ Todo guardado</span>
    </div>
  </div>

  <div id="songs-container">
    <div class="text-center py-5">
      <div class="game-spinner mx-auto"></div>
      <div class="mt-3 small" style="color:var(--muted)">Cargando canciones…</div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';
let allSongs = [];

async function loadSongs() {
  const r = await fetch(`${API}?action=get_songs`);
  allSongs = await r.json();

  // Poblar selector de géneros
  const genres = [...new Set(allSongs.map(s => s.genre).filter(Boolean))].sort();
  const sel = document.getElementById('filter-genre');
  genres.forEach(g => sel.insertAdjacentHTML('beforeend', `<option value="${esc(g)}">${esc(g)}</option>`));

  renderSongs(allSongs);
}

function filterSongs() {
  const txt   = document.getElementById('filter-text').value.toLowerCase();
  const genre = document.getElementById('filter-genre').value;
  const filtered = allSongs.filter(s =>
    (!txt   || s.title.toLowerCase().includes(txt) || s.artist.toLowerCase().includes(txt)) &&
    (!genre || s.genre === genre)
  );
  renderSongs(filtered);
}

function renderSongs(songs) {
  document.getElementById('count-label').textContent = `${songs.length} canciones`;
  const c = document.getElementById('songs-container');
  if (!songs.length) { c.innerHTML = '<div class="text-center py-4 text-secondary">Sin resultados</div>'; return; }

  // Agrupar por género
  const byGenre = {};
  songs.forEach(s => { (byGenre[s.genre || 'Sin género'] ??= []).push(s); });

  let html = '';
  for (const [genre, list] of Object.entries(byGenre)) {
    html += `<h6 class="fw-bold mt-4 mb-2 text-uppercase small" style="color:var(--accent)">${esc(genre)}</h6>
    <div class="card mb-3 p-0 overflow-hidden">
    <table class="table table-dark table-hover mb-0" style="--bs-table-bg:var(--card);--bs-table-hover-bg:rgba(255,255,255,.04)">
      <thead><tr>
        <th class="small" style="width:35%">Canción</th>
        <th class="small" style="width:12%">Año</th>
        <th class="small">Spotify URL</th>
        <th class="small">YouTube URL</th>
        <th></th>
      </tr></thead>
      <tbody>`;
    list.forEach(s => {
      html += `
      <tr class="song-row" data-id="${s.id}">
        <td>
          <div class="fw-semibold small">${esc(s.title)}</div>
          <div style="font-size:.75rem;color:var(--muted)">${esc(s.artist)}</div>
        </td>
        <td class="small">${s.year}</td>
        <td>
          <input type="url" class="form-control url-input spotify-input"
                 placeholder="https://open.spotify.com/track/…"
                 value="${esc(s.spotify_url)}">
        </td>
        <td>
          <input type="url" class="form-control url-input youtube-input"
                 placeholder="https://youtube.com/watch?v=…"
                 value="${esc(s.youtube_url)}">
        </td>
        <td>
          <div class="d-flex align-items-center gap-2 flex-nowrap">
            <button class="btn btn-sm btn-game rounded-pill save-btn" onclick="saveSong(this, ${s.id})">Guardar</button>
            <span class="saved-ok ${(s.spotify_url || s.youtube_url) ? '' : 'd-none'}">✓</span>
          </div>
        </td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
  }
  c.innerHTML = html;
}

async function saveSong(btn, id) {
  const row     = btn.closest('tr');
  const spotify = row.querySelector('.spotify-input').value.trim();
  const youtube = row.querySelector('.youtube-input').value.trim();
  const ok      = row.querySelector('.saved-ok');

  btn.disabled = true;
  btn.textContent = '…';

  const res = await fetch(`${API}?action=update_song_links`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ song_id: id, spotify_url: spotify, youtube_url: youtube }),
  }).then(r => r.json());

  btn.disabled = false;
  btn.textContent = 'Guardar';

  if (res.error) { alert(res.error); return; }

  // Actualizar allSongs en memoria
  const s = allSongs.find(x => x.id == id);
  if (s) { s.spotify_url = spotify; s.youtube_url = youtube; }

  ok.classList.remove('d-none');
}

async function saveAll(btn) {
  const rows = document.querySelectorAll('.song-row');
  if (!rows.length) return;

  btn.disabled = true;
  btn.textContent = '⏳ Guardando…';

  let errors = 0;
  await Promise.all([...rows].map(async row => {
    const id      = row.dataset.id;
    const spotify = row.querySelector('.spotify-input').value.trim();
    const youtube = row.querySelector('.youtube-input').value.trim();

    const res = await fetch(`${API}?action=update_song_links`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ song_id: id, spotify_url: spotify, youtube_url: youtube }),
    }).then(r => r.json());

    if (res.error) { errors++; return; }

    const s = allSongs.find(x => x.id == id);
    if (s) { s.spotify_url = spotify; s.youtube_url = youtube; }
  }));

  btn.disabled = false;
  btn.textContent = '💾 Guardar todo';

  if (errors) { alert(`${errors} canción(es) no se pudieron guardar.`); return; }

  const ok = document.getElementById('save-all-ok');
  ok.classList.remove('d-none');
  setTimeout(() => ok.classList.add('d-none'), 2500);
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

loadSongs();
</script>
</body>
</html>
