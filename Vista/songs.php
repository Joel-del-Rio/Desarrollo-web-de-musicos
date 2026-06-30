<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Catálogo de canciones</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
  <style>
    .filter-bar { position: sticky; top: 0; z-index: 10; background: var(--bg); padding: .75rem 0; }

    /* ── Mini reproductor por fila ── */
    .song-play-btn {
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 50%;
      background: rgba(233,69,96,.15); border: 1.5px solid rgba(233,69,96,.5);
      color: var(--accent); cursor: pointer; flex-shrink: 0;
      transition: background .15s;
    }
    .song-play-btn:hover  { background: rgba(233,69,96,.3); }
    .song-play-btn:disabled { opacity: .4; cursor: default; }

    .song-player-ctrl {
      display: flex; align-items: center; gap: 6px;
      flex: 1; min-width: 0;
    }
    .song-bar {
      flex: 1; height: 5px; background: rgba(255,255,255,.12);
      border-radius: 3px; cursor: pointer; position: relative; min-width: 60px;
    }
    .song-bar-fill {
      height: 100%; background: var(--accent);
      border-radius: 3px; width: 0%; pointer-events: none;
    }
    .song-time {
      font-size: .68rem; color: var(--muted); white-space: nowrap; flex-shrink: 0;
    }
    .song-vol-wrap {
      display: flex; align-items: center; gap: 4px; flex-shrink: 0;
    }
    .song-vol-wrap svg { opacity: .45; flex-shrink: 0; }
    .song-vol {
      width: 60px; accent-color: var(--accent);
      -webkit-appearance: none; appearance: none;
      height: 4px; border-radius: 2px; background: rgba(255,255,255,.15);
      cursor: pointer;
    }
    .song-vol::-webkit-slider-thumb {
      -webkit-appearance: none; width: 12px; height: 12px;
      border-radius: 50%; background: var(--accent);
    }

    /* la celda derecha ocupa el espacio del reproductor */
    .col-player { width: 260px; }
  </style>
</head>
<body>

<div class="container py-4" style="max-width:1040px">

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
        <th class="small">Canción</th>
        <th class="small" style="width:70px">Año</th>
        <th class="small" style="width:160px">Links</th>
        <th class="small col-player">Reproducir</th>
      </tr></thead>
      <tbody>`;
    list.forEach((s, idx) => {
      const uid = `s${s.id ?? (genre + idx)}`;
      const q   = encodeURIComponent(s.title + ' ' + s.artist);
      html += `
      <tr>
        <td>
          <div class="fw-semibold small">${esc(s.title)}</div>
          <div style="font-size:.75rem;color:var(--muted)">${esc(s.artist)}</div>
        </td>
        <td class="small">${s.year}</td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <a href="https://open.spotify.com/search/${q}" target="_blank" rel="noopener"
               class="btn-stream btn-spotify" style="font-size:.75rem;padding:.25rem .6rem">Spotify</a>
            <a href="https://www.youtube.com/results?search_query=${q}" target="_blank" rel="noopener"
               class="btn-stream btn-youtube" style="font-size:.75rem;padding:.25rem .6rem">YouTube</a>
          </div>
        </td>
        <td>
          <div class="d-flex align-items-center gap-2" id="wrap-${uid}">
            <!-- Botón play -->
            <button class="song-play-btn" id="btn-${uid}"
                    data-title="${esc(s.title)}" data-artist="${esc(s.artist)}" data-uid="${uid}"
                    onclick="playSong(this)" title="Escuchar preview (30s)">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            </button>
            <!-- Controles (ocultos hasta que se reproduce) -->
            <div class="song-player-ctrl d-none" id="ctrl-${uid}">
              <div class="song-bar" id="bar-${uid}" onclick="seekSong('${uid}', event, this)">
                <div class="song-bar-fill" id="fill-${uid}"></div>
              </div>
              <span class="song-time" id="time-${uid}">0:00</span>
            </div>
            <!-- Volumen (aparece con los controles) -->
            <div class="song-vol-wrap d-none" id="vol-wrap-${uid}">
              <svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
              <input type="range" class="song-vol" id="vol-${uid}" min="0" max="100" value="70"
                     oninput="setVol(this.value)">
            </div>
          </div>
        </td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
  }
  c.innerHTML = html;
}

/* ── Reproductor compartido ── */
const SVG_PLAY  = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>`;
const SVG_PAUSE = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>`;
const SVG_LOAD  = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>`;

const audio = new Audio();
let activeUid = null;
let volLevel  = 0.7;

audio.volume = volLevel;

audio.addEventListener('timeupdate', () => {
  if (!activeUid || !audio.duration) return;
  document.getElementById('fill-' + activeUid).style.width = (audio.currentTime / audio.duration * 100) + '%';
  document.getElementById('time-' + activeUid).textContent =
    fmtTime(audio.currentTime) + ' / ' + fmtTime(audio.duration);
});

audio.addEventListener('ended', () => {
  if (activeUid) resetRow(activeUid);
  activeUid = null;
});

async function playSong(btn) {
  const uid    = btn.dataset.uid;
  const title  = btn.dataset.title;
  const artist = btn.dataset.artist;

  // Mismo botón → pausar/reanudar
  if (activeUid === uid) {
    if (audio.paused) {
      await audio.play().catch(() => {});
      btn.innerHTML = SVG_PAUSE;
    } else {
      audio.pause();
      btn.innerHTML = SVG_PLAY;
    }
    return;
  }

  // Parar fila anterior
  if (activeUid) resetRow(activeUid);

  activeUid = uid;
  btn.innerHTML = SVG_LOAD;
  btn.disabled  = true;

  try {
    const q   = encodeURIComponent(title + ' ' + artist);
    const res = await fetch(`https://itunes.apple.com/search?term=${q}&media=music&entity=song&limit=5`);
    const d   = await res.json();
    const hit = d.results?.find(t => t.previewUrl);

    if (!hit?.previewUrl) {
      btn.innerHTML = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>`;
      btn.title    = 'Preview no disponible';
      btn.disabled = false;
      activeUid    = null;
      return;
    }

    audio.src    = hit.previewUrl;
    audio.volume = volLevel;
    audio.load();
    await audio.play();
    btn.innerHTML = SVG_PAUSE;
    btn.disabled  = false;

    // Mostrar controles y volumen
    document.getElementById('ctrl-'     + uid).classList.remove('d-none');
    document.getElementById('vol-wrap-' + uid).classList.remove('d-none');
    // Sincronizar slider de volumen
    const volEl = document.getElementById('vol-' + uid);
    if (volEl) volEl.value = Math.round(volLevel * 100);

  } catch {
    btn.innerHTML = SVG_PLAY;
    btn.disabled  = false;
    activeUid     = null;
  }
}

function seekSong(uid, e, bar) {
  if (!audio.duration) return;
  const rect = bar.getBoundingClientRect();
  audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
}

function setVol(val) {
  volLevel     = val / 100;
  audio.volume = volLevel;
}

function resetRow(uid) {
  audio.pause();
  const btn = document.getElementById('btn-' + uid);
  if (btn) { btn.innerHTML = SVG_PLAY; btn.disabled = false; }
  document.getElementById('ctrl-'     + uid)?.classList.add('d-none');
  document.getElementById('vol-wrap-' + uid)?.classList.add('d-none');
  const fill = document.getElementById('fill-' + uid);
  if (fill) fill.style.width = '0%';
  const time = document.getElementById('time-' + uid);
  if (time) time.textContent = '0:00';
}

function fmtTime(s) {
  const m = Math.floor(s / 60);
  return `${m}:${Math.floor(s % 60).toString().padStart(2,'0')}`;
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

loadSongs();
</script>
</body>
</html>
