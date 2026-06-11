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
    .badge-genre { font-size: .7rem; font-weight: 600; background: rgba(233,69,96,.15); color: var(--accent); border: 1px solid rgba(233,69,96,.3); }
    .filter-bar  { position: sticky; top: 0; z-index: 10; background: var(--bg); padding: .75rem 0; }
  </style>
</head>
<body>

<div class="container py-4" style="max-width:860px">

  <div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="height:48px">
    <div>
      <h4 class="fw-black mb-0">Catálogo de canciones</h4>
      <div class="small" style="color:var(--muted)">Consulta las canciones disponibles</div>
    </div>
    <a href="<?= BASE_URL ?>/Vista/admin.php" class="btn btn-outline-secondary btn-sm rounded-pill ms-auto">‹ Volver al panel</a>
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

  const byGenre = {};
  songs.forEach(s => { (byGenre[s.genre || 'Sin género'] ??= []).push(s); });

  let html = '';
  for (const [genre, list] of Object.entries(byGenre)) {
    html += `<h6 class="fw-bold mt-4 mb-2 text-uppercase small" style="color:var(--accent)">${esc(genre)}</h6>
    <div class="card mb-3 p-0 overflow-hidden">
    <table class="table table-dark table-hover mb-0" style="--bs-table-bg:var(--card);--bs-table-hover-bg:rgba(255,255,255,.04)">
      <thead><tr>
        <th class="small" style="width:40%">Canción</th>
        <th class="small" style="width:10%">Año</th>
        <th class="small">Links</th>
      </tr></thead>
      <tbody>`;
    list.forEach(s => {
      const q = encodeURIComponent(s.title + ' ' + s.artist);
      html += `
      <tr>
        <td>
          <div class="fw-semibold small">${esc(s.title)}</div>
          <div style="font-size:.75rem;color:var(--muted)">${esc(s.artist)}</div>
        </td>
        <td class="small">${s.year}</td>
        <td>
          <div class="d-flex gap-2">
            <a href="https://open.spotify.com/search/${q}" target="_blank" rel="noopener"
               class="btn-stream btn-spotify" style="font-size:.75rem;padding:.25rem .6rem">
              Spotify
            </a>
            <a href="https://www.youtube.com/results?search_query=${q}" target="_blank" rel="noopener"
               class="btn-stream btn-youtube" style="font-size:.75rem;padding:.25rem .6rem">
              YouTube
            </a>
          </div>
        </td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
  }
  c.innerHTML = html;
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

loadSongs();
</script>
</body>
</html>
