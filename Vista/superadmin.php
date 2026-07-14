<?php
/**
 * superadmin.php — Panel de supervisión global de Hitstoric
 *
 * Acceso protegido con las mismas credenciales de administrador.
 * Muestra estadísticas globales, historial de partidas y detalle de cada una.
 */
require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Superadmin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=7">
  <style>
    html { scroll-behavior: smooth; }
    .stat-card {
      background: var(--card);
      border: 1.5px solid rgba(255,255,255,.08);
      border-radius: 14px;
      padding: 1.1rem 1.3rem;
      text-align: center;
    }
    .stat-value { font-size: 2rem; font-weight: 900; color: var(--accent); line-height: 1.1; }
    .stat-label { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-top: .25rem; }

    .status-badge {
      font-size: .7rem; font-weight: 700; padding: .2rem .55rem; border-radius: 50px; white-space: nowrap;
    }
    .status-waiting  { background: rgba(255,193,7,.15);  color: #ffc107; }
    .status-question { background: rgba(13,202,240,.15); color: #0dcaf0; }
    .status-results  { background: rgba(25,135,84,.15);  color: #198754; }
    .status-finished { background: rgba(108,117,125,.15);color: #6c757d; }

    .game-row { cursor: pointer; transition: background .15s; }
    .game-row:hover td { background: rgba(255,255,255,.04) !important; }

    .detail-panel {
      background: var(--bg2);
      border: 1.5px solid rgba(255,255,255,.08);
      border-radius: 12px;
      padding: 1.25rem;
      margin-top: .5rem;
    }
    .player-rank { width: 2rem; text-align: center; font-weight: 900; color: var(--muted); }
    .player-rank.gold   { color: #ffd700; }
    .player-rank.silver { color: #c0c0c0; }
    .player-rank.bronze { color: #cd7f32; }

    #login-screen { min-height: 100vh; display: flex; align-items: center; justify-content: center; }

    .search-bar { background: var(--card); border: 1.5px solid rgba(255,255,255,.1); border-radius: 10px;
                  color: #fff; padding: .45rem .9rem; width: 100%; outline: none; font-size: .9rem; }
    .search-bar::placeholder { color: var(--muted); }
    .search-bar:focus { border-color: var(--accent); }

    /* ── Buscador de canciones ── */
    .song-hit {
      display: flex; align-items: center; gap: .75rem;
      padding: .6rem .9rem; border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .song-hit:last-child { border-bottom: none; }
    .song-hit img { width: 42px; height: 42px; border-radius: 6px; flex-shrink: 0; }
    .song-hit-info { flex: 1; min-width: 0; }
    .song-hit-title { font-weight: 600; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .song-hit-sub { font-size: .76rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .song-play-btn {
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
      background: rgba(233,69,96,.15); border: 1.5px solid rgba(233,69,96,.5);
      color: var(--accent); cursor: pointer;
      transition: background .15s;
    }
    .song-play-btn:hover  { background: rgba(233,69,96,.3); }
    .song-play-btn:disabled { opacity: .4; cursor: default; }

    .song-player-ctrl { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
    .song-bar {
      flex: 1; height: 5px; background: rgba(255,255,255,.12);
      border-radius: 3px; cursor: pointer; position: relative; min-width: 60px;
    }
    .song-bar-fill { height: 100%; background: var(--accent); border-radius: 3px; width: 0%; pointer-events: none; }
    .song-time { font-size: .68rem; color: var(--muted); white-space: nowrap; flex-shrink: 0; }
    .song-vol-wrap { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
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

    /* ── Catálogo actual ── */
    .catalog-row {
      display: flex; align-items: center; gap: .75rem;
      padding: .55rem .9rem; border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .catalog-row img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; flex-shrink: 0; }
    .catalog-row:last-child { border-bottom: none; }
    .catalog-row-info { flex: 1; min-width: 0; }
    .catalog-row-title { font-weight: 600; font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .catalog-row-sub { font-size: .74rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .catalog-del-btn {
      display: flex; align-items: center; justify-content: center;
      width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
      background: transparent; border: 1.5px solid rgba(255,255,255,.15);
      color: var(--muted); cursor: pointer; transition: all .15s; font-size: .85rem;
    }
    .catalog-del-btn:hover { background: rgba(220,53,69,.15); border-color: rgba(220,53,69,.5); color: #dc3545; }
    .catalog-del-btn:disabled { opacity: .4; cursor: default; }

    .genre-group:not(:last-child) { border-bottom: 1px solid rgba(255,255,255,.08); }
    .genre-group-header {
      display: flex; align-items: center; justify-content: space-between;
      width: 100%; padding: .7rem 1rem; cursor: pointer;
      color: #fff; font-weight: 700; font-size: .82rem;
      text-transform: uppercase; letter-spacing: .05em;
    }
    .genre-group-header:hover { background: rgba(255,255,255,.03); }
    .genre-group-count { color: var(--muted); font-weight: 400; text-transform: none; letter-spacing: 0; margin-left: .5rem; }
    .genre-group-header .chevron { transition: transform .2s; flex-shrink: 0; }
    .genre-group-header.collapsed .chevron { transform: rotate(-90deg); }

    /* ── Navegación rápida entre secciones ── */
    .song-section-nav a {
      display: block; padding: .45rem .8rem; border-radius: 8px;
      color: var(--muted); font-size: .85rem; font-weight: 600;
      text-decoration: none; transition: all .15s; white-space: nowrap;
    }
    .song-section-nav a:hover { background: rgba(255,255,255,.06); color: #fff; }
    .song-section-nav a.active { background: rgba(233,69,96,.15); color: var(--accent); }
  </style>
</head>
<body>

<!-- ══ PANTALLA DE LOGIN ══ -->
<div id="login-screen">
  <div style="width:100%;max-width:360px;padding:1.5rem">
    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:80%;max-width:260px">
      <div class="mt-3 fw-bold text-secondary small text-uppercase">Panel Superadmin</div>
    </div>
    <div class="card p-4">
      <div class="mb-3">
        <label class="form-label small text-secondary fw-semibold text-uppercase">Email</label>
        <input type="email" id="sa-email" class="form-control" placeholder="admin@ejemplo.com" autocomplete="email">
      </div>
      <div class="mb-3">
        <label class="form-label small text-secondary fw-semibold text-uppercase">Contraseña</label>
        <input type="password" id="sa-pass" class="form-control" autocomplete="current-password"
               onkeydown="if(event.key==='Enter') saLogin()">
      </div>
      <div id="sa-login-err" class="alert alert-danger py-2 small d-none mb-3"></div>
      <button class="btn btn-game w-100 rounded-pill fw-bold" onclick="saLogin()">Entrar</button>
    </div>
    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Inicio</a>
    </div>
  </div>
</div>

<!-- ══ PANEL PRINCIPAL (oculto hasta login) ══ -->
<div id="main-panel" class="d-none">

  <!-- Navbar -->
  <nav class="navbar px-3 py-2" style="background:var(--card);border-bottom:1.5px solid rgba(255,255,255,.07)">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="height:30px">
      <span class="fw-black text-secondary small text-uppercase" style="letter-spacing:.08em">Superadmin</span>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto">
      <span id="sa-nav-status" class="text-secondary small"></span>
      <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">‹ Inicio</a>
    </div>
  </nav>

  <div class="container-fluid py-4 px-3 px-md-4" style="max-width:1100px">

    <!-- Pestañas -->
    <ul class="nav nav-tabs mb-4" id="sa-tabs">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-partidas" type="button">Partidas</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-canciones" type="button" onclick="loadCatalog();loadGenres()">Canciones</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-memes" type="button" onclick="loadMemeCatalog();loadGenres()">Memes</button>
      </li>
    </ul>

    <div class="tab-content">
    <!-- ══ PESTAÑA: PARTIDAS ══ -->
    <div class="tab-pane fade show active" id="tab-partidas">

    <!-- Estadísticas globales -->
    <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="letter-spacing:.08em">Estadísticas globales</h6>
    <div class="row g-3 mb-4" id="stats-row">
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-total-games">—</div><div class="stat-label">Partidas totales</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-active-games">—</div><div class="stat-label">Partidas activas</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-total-players">—</div><div class="stat-label">Jugadores totales</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-correct-pct">—</div><div class="stat-label">Aciertos</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-avg-players">—</div><div class="stat-label">Media jugadores/partida</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-top-genre">—</div><div class="stat-label">Género más jugado</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" id="s-total-answers">—</div><div class="stat-label">Respuestas totales</div></div></div>
    </div>

    <!-- Historial de partidas -->
    <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
      <h6 class="text-secondary text-uppercase fw-semibold mb-0" style="letter-spacing:.08em">Historial de partidas</h6>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <input type="search" id="game-search" class="search-bar" style="max-width:240px" placeholder="Buscar PIN, género, email…" oninput="filterGames()">
      </div>
    </div>

    <div class="card p-0" style="overflow:hidden">
      <div style="overflow-x:auto">
        <table class="table table-dark table-sm mb-0" style="font-size:.85rem">
          <thead>
            <tr style="border-bottom:1.5px solid rgba(255,255,255,.1)">
              <th class="ps-3">PIN</th>
              <th>Estado</th>
              <th>Género</th>
              <th>Ronda</th>
              <th>Jugadores</th>
              <th>Modo</th>
              <th>Organizador</th>
              <th>Creada</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="games-tbody">
            <tr><td colspan="9" class="text-center py-4 text-secondary">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    </div>
    <!-- ══ PESTAÑA: CANCIONES ══ -->
    <div class="tab-pane fade" id="tab-canciones">
      <div class="d-flex gap-4 align-items-start">

        <!-- Navegación rápida entre secciones -->
        <div class="d-none d-md-block song-section-nav" style="position:sticky; top:1rem; min-width:170px">
          <a href="#section-add" data-target="section-add">Añadir canción</a>
          <a href="#section-catalog" data-target="section-catalog">Catálogo actual</a>
          <a href="#section-genres" data-target="section-genres">Gestionar géneros</a>
        </div>

        <div style="flex:1; min-width:0">

          <div id="section-add">
            <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="letter-spacing:.08em">Añadir canción al catálogo</h6>

            <div class="card p-3 mb-3">
              <div class="d-flex gap-2 flex-wrap align-items-end">
                <div style="flex:1;min-width:220px">
                  <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Buscar canción</label>
                  <input type="search" id="song-search-input" class="search-bar" placeholder="Título o artista…"
                         onkeydown="if(event.key==='Enter'){event.preventDefault();searchSongs();}">
                </div>
                <button class="btn btn-game rounded-pill fw-bold px-4" onclick="searchSongs()">Buscar</button>
              </div>
            </div>

            <div id="song-results" class="card p-0" style="overflow:hidden;display:none"></div>
            <div id="song-search-status" class="text-secondary small mt-2"></div>
            <div id="song-pages" class="d-flex gap-2 justify-content-center mt-3 flex-wrap"></div>
          </div>

          <div id="section-catalog" class="mt-5">
            <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
              <h6 class="text-secondary text-uppercase fw-semibold mb-0" style="letter-spacing:.08em">Catálogo actual</h6>
              <div class="d-flex gap-2 align-items-center flex-wrap">
                <input type="search" id="catalog-search" class="search-bar" style="max-width:240px" placeholder="Buscar título, artista o género…" oninput="filterCatalog()">
                <span class="small" id="catalog-count" style="color:var(--muted)"></span>
              </div>
            </div>

            <div id="catalog-list" class="card p-0" style="overflow:hidden">
              <div class="text-center py-4 text-secondary">Cargando…</div>
            </div>
          </div>

          <div id="section-genres" class="mt-5">
            <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="letter-spacing:.08em">Gestionar géneros</h6>

            <div class="card p-3 mb-3">
              <div class="d-flex gap-2 flex-wrap align-items-end">
                <div style="flex:1;min-width:220px">
                  <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Nuevo género</label>
                  <input type="text" id="new-genre-input" class="search-bar" placeholder="Nombre del género…"
                         onkeydown="if(event.key==='Enter'){event.preventDefault();addGenre();}">
                </div>
                <button class="btn btn-game rounded-pill fw-bold px-4" onclick="addGenre()">Añadir género</button>
              </div>
            </div>

            <div id="genre-list" class="card p-0" style="overflow:hidden">
              <div class="text-center py-4 text-secondary">Cargando…</div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══ PESTAÑA: MEMES ══ -->
    <div class="tab-pane fade" id="tab-memes">

      <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="letter-spacing:.08em">Subir meme al catálogo</h6>

      <div class="card p-3 mb-3">
        <form id="meme-upload-form" onsubmit="event.preventDefault();uploadMeme();">
          <div class="d-flex gap-2 flex-wrap align-items-end">
            <div style="flex:1;min-width:200px">
              <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Imagen</label>
              <input type="file" id="meme-image-input" class="form-control form-control-sm" accept="image/png,image/jpeg,image/gif,image/webp" required>
            </div>
            <div style="min-width:120px">
              <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Año</label>
              <input type="number" id="meme-year-input" class="search-bar" min="1900" max="2100" placeholder="Año" required>
            </div>
            <div style="min-width:170px">
              <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Género</label>
              <select id="meme-genre-select" class="form-select form-select-sm" required></select>
            </div>
            <div style="flex:1;min-width:180px">
              <label class="form-label small text-secondary fw-semibold text-uppercase mb-1">Título (opcional)</label>
              <input type="text" id="meme-title-input" class="search-bar" placeholder="Descripción…">
            </div>
            <button type="submit" class="btn btn-game rounded-pill fw-bold px-4">Subir meme</button>
          </div>
        </form>
        <div id="meme-upload-status" class="small mt-2" style="color:var(--muted)"></div>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-3 mt-5 gap-2 flex-wrap">
        <h6 class="text-secondary text-uppercase fw-semibold mb-0" style="letter-spacing:.08em">Catálogo de memes</h6>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <input type="search" id="meme-catalog-search" class="search-bar" style="max-width:240px" placeholder="Buscar título o género…" oninput="filterMemeCatalog()">
          <span class="small" id="meme-catalog-count" style="color:var(--muted)"></span>
        </div>
      </div>

      <div id="meme-catalog-list" class="card p-0" style="overflow:hidden">
        <div class="text-center py-4 text-secondary">Cargando…</div>
      </div>

    </div>
    </div>

  </div>
</div>

<!-- Modal detalle partida -->
<div class="modal fade" id="gameDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background:var(--bg)">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-black" id="detail-title">Partida #—</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detail-body">
        <div class="text-center py-4 text-secondary">Cargando…</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';
const MEME_IMG_BASE = '<?= BASE_URL ?>/assets/images/memes/';
let allGames = [];
let detailModal;
let allCatalogSongs = [];
let allMemeCatalog = [];
let songHitsData = [];
let SONG_GENRES = [];    // nombres (para el selector de la búsqueda)
let allGenres = [];      // {id, name} (para el panel de gestión)

document.addEventListener('DOMContentLoaded', () => {
  detailModal = new bootstrap.Modal(document.getElementById('gameDetailModal'));
  // Si ya hemos autenticado en esta sesión, saltamos el login
  if (sessionStorage.getItem('sa_auth') === '1') showPanel();
  initSongSectionNav();
});

/* Resalta el enlace de la sección visible en la navegación rápida de la pestaña Canciones */
function initSongSectionNav() {
  const links = document.querySelectorAll('.song-section-nav a');
  if (!links.length) return;
  const setActive = id => links.forEach(a => a.classList.toggle('active', a.dataset.target === id));
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); });
  }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });
  ['section-add', 'section-catalog', 'section-genres'].forEach(id => {
    const el = document.getElementById(id);
    if (el) observer.observe(el);
  });
  setActive('section-add');
}

async function saLogin() {
  const email = document.getElementById('sa-email').value.trim();
  const pass  = document.getElementById('sa-pass').value;
  const errEl = document.getElementById('sa-login-err');
  errEl.classList.add('d-none');

  const r = await fetch(`${API}?action=superadmin_login`, {
    method: 'POST',
    body: new URLSearchParams({ email, password: pass }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    sessionStorage.setItem('sa_auth', '1');
    showPanel();
  } else {
    errEl.textContent = r.error || 'Credenciales incorrectas';
    errEl.classList.remove('d-none');
    document.getElementById('sa-pass').value = '';
    document.getElementById('sa-pass').focus();
  }
}

function showPanel() {
  document.getElementById('login-screen').classList.add('d-none');
  document.getElementById('main-panel').classList.remove('d-none');
  loadStats();
  loadGames();
}

async function loadStats() {
  const r = await fetch(`${API}?action=superadmin_stats`).then(r => r.json()).catch(() => ({}));
  if (!r.stats) return;
  const s = r.stats;
  document.getElementById('s-total-games').textContent   = s.total_games;
  document.getElementById('s-active-games').textContent  = s.active_games;
  document.getElementById('s-total-players').textContent = s.total_players;
  document.getElementById('s-total-answers').textContent = s.total_answers;
  document.getElementById('s-avg-players').textContent   = s.avg_players;
  document.getElementById('s-top-genre').textContent     = s.top_genre;
  const pct = s.total_answers > 0 ? Math.round(s.correct_answers / s.total_answers * 100) : 0;
  document.getElementById('s-correct-pct').textContent   = pct + '%';
  document.getElementById('sa-nav-status').textContent   = `${s.active_games} activa${s.active_games !== 1 ? 's' : ''}`;
}

async function loadGames() {
  const r = await fetch(`${API}?action=superadmin_games`).then(r => r.json()).catch(() => ({}));
  allGames = r.games || [];
  renderGames(allGames);
}

function filterGames() {
  const q = document.getElementById('game-search').value.toLowerCase();
  renderGames(allGames.filter(g =>
    (g.pin || '').includes(q) ||
    (g.selected_genre || '').toLowerCase().includes(q) ||
    (g.organizer_email || '').toLowerCase().includes(q) ||
    (g.status || '').includes(q)
  ));
}

function statusBadge(s) {
  const map = { waiting: ['Esperando','waiting'], question: ['En pregunta','question'],
                results: ['Resultados','results'], finished: ['Finalizada','finished'] };
  const [label, cls] = map[s] || [s, 'waiting'];
  return `<span class="status-badge status-${cls}">${label}</span>`;
}

function fmtDate(dt) {
  if (!dt) return '—';
  const d = new Date(dt);
  return d.toLocaleDateString('es-ES', { day:'2-digit', month:'2-digit', year:'2-digit' })
       + ' ' + d.toLocaleTimeString('es-ES', { hour:'2-digit', minute:'2-digit' });
}

function renderGames(games) {
  const tbody = document.getElementById('games-tbody');
  if (!games.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-secondary">No hay partidas</td></tr>';
    return;
  }
  tbody.innerHTML = games.map(g => `
    <tr class="game-row" onclick="openDetail(${g.id})">
      <td class="ps-3 fw-black" style="color:var(--accent);letter-spacing:.1em">${g.pin}</td>
      <td>${statusBadge(g.status)}</td>
      <td>${g.selected_genre || '—'}</td>
      <td class="text-secondary">${g.current_round}/${g.total_rounds}</td>
      <td><strong>${g.player_count}</strong></td>
      <td class="text-secondary" style="font-size:.78rem">${g.pin_mode === 'individual' ? 'Individual' : 'Compartido'}</td>
      <td class="text-secondary" style="font-size:.78rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${g.organizer_email || '—'}</td>
      <td class="text-secondary" style="font-size:.78rem;white-space:nowrap">${fmtDate(g.created_at)}</td>
      <td class="pe-3 text-secondary" style="font-size:.8rem">›</td>
    </tr>
  `).join('');
}

async function openDetail(gameId) {
  document.getElementById('detail-title').textContent = `Partida #${gameId}`;
  document.getElementById('detail-body').innerHTML = '<div class="text-center py-4 text-secondary">Cargando…</div>';
  detailModal.show();

  const r = await fetch(`${API}?action=superadmin_game_detail&game_id=${gameId}`)
    .then(r => r.json()).catch(() => ({}));

  if (!r.success) {
    document.getElementById('detail-body').innerHTML = `<div class="alert alert-danger">${r.error || 'Error'}</div>`;
    return;
  }

  const g = r.game;
  const players = r.players || [];
  const songs   = r.songs   || [];

  const rankIcon = i => i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i+1}.`;

  const playersHtml = players.length ? `
    <table class="table table-dark table-sm mb-0" style="font-size:.84rem">
      <thead><tr>
        <th style="width:2.5rem"></th>
        <th>Jugador</th>
        <th class="text-end">Puntos</th>
        <th class="text-end">Racha</th>
        <th>Email</th>
      </tr></thead>
      <tbody>${players.map((p, i) => `
        <tr>
          <td class="text-center fw-bold" style="color:${i<3?'var(--accent)':'var(--muted)'}">${rankIcon(i)}</td>
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.avatar_color};margin-right:.4rem"></span>
            ${p.name}
          </td>
          <td class="text-end fw-bold">${p.score.toLocaleString('es-ES')}</td>
          <td class="text-end">${p.streak > 0 ? '🔥 ' + p.streak : '—'}</td>
          <td class="text-secondary" style="font-size:.78rem">${p.email || '—'}</td>
        </tr>`).join('')}
      </tbody>
    </table>` : '<p class="text-secondary small">Sin jugadores</p>';

  const songsHtml = songs.length ? `
    <table class="table table-dark table-sm mb-0" style="font-size:.82rem">
      <thead><tr><th>#</th><th>Canción</th><th>Artista</th><th>Año</th><th>Género</th></tr></thead>
      <tbody>${songs.map(s => `
        <tr>
          <td class="text-secondary">${s.round_number}</td>
          <td>${s.title}</td>
          <td class="text-secondary">${s.artist}</td>
          <td>${s.year}</td>
          <td class="text-secondary">${s.genre || '—'}</td>
        </tr>`).join('')}
      </tbody>
    </table>` : '<p class="text-secondary small">Sin canciones</p>';

  document.getElementById('detail-body').innerHTML = `
    <!-- Info de la partida -->
    <div class="row g-2 mb-4">
      <div class="col-6 col-md-3"><div class="stat-card" style="padding:.8rem"><div class="stat-value" style="font-size:1.5rem">${g.pin}</div><div class="stat-label">PIN</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card" style="padding:.8rem">${statusBadge(g.status)}<div class="stat-label mt-1">Estado</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card" style="padding:.8rem"><div class="stat-value" style="font-size:1.5rem">${g.current_round}/${g.total_rounds}</div><div class="stat-label">Ronda</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card" style="padding:.8rem"><div class="stat-value" style="font-size:1.5rem">${players.length}</div><div class="stat-label">Jugadores</div></div></div>
    </div>
    <div class="mb-1 text-secondary small"><strong>Género:</strong> ${g.selected_genre} &nbsp;·&nbsp; <strong>Tiempo:</strong> ${g.question_time}s &nbsp;·&nbsp; <strong>Modo:</strong> ${g.pin_mode} &nbsp;·&nbsp; <strong>Creada:</strong> ${fmtDate(g.created_at)}</div>
    ${g.organizer_email ? `<div class="mb-3 text-secondary small"><strong>Organizador:</strong> ${g.organizer_email}</div>` : '<div class="mb-3"></div>'}

    <!-- Clasificación -->
    <h6 class="fw-bold mb-2">Clasificación</h6>
    <div class="card p-0 mb-4" style="overflow:hidden">${playersHtml}</div>

    <!-- Canciones -->
    <h6 class="fw-bold mb-2">Canciones jugadas</h6>
    <div class="card p-0" style="overflow:hidden">${songsHtml}</div>
  `;
}

/* ══ Buscador de canciones (pestaña Canciones) ══ */

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── Reproductor de previews en resultados de búsqueda ── */
const SVG_PLAY  = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>`;
const SVG_PAUSE = `<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>`;

const songAudio = new Audio();
let songPreviewUrls = [];
let activePreviewIdx = null;
let previewVolLevel = 0.7;
songAudio.volume = previewVolLevel;

songAudio.addEventListener('timeupdate', () => {
  if (activePreviewIdx === null || !songAudio.duration) return;
  const i = activePreviewIdx;
  const fill = document.getElementById(`fill-${i}`);
  const time = document.getElementById(`time-${i}`);
  if (fill) fill.style.width = (songAudio.currentTime / songAudio.duration * 100) + '%';
  if (time) time.textContent = fmtTime(songAudio.currentTime) + ' / ' + fmtTime(songAudio.duration);
});

songAudio.addEventListener('ended', () => {
  if (activePreviewIdx !== null) resetPreviewRow(activePreviewIdx);
  activePreviewIdx = null;
});

function resetPreviewRow(i) {
  const btn = document.getElementById(`play-${i}`);
  if (btn) btn.innerHTML = SVG_PLAY;
  document.getElementById(`ctrl-${i}`)?.classList.add('d-none');
  document.getElementById(`vol-wrap-${i}`)?.classList.add('d-none');
  const fill = document.getElementById(`fill-${i}`);
  if (fill) fill.style.width = '0%';
  const time = document.getElementById(`time-${i}`);
  if (time) time.textContent = '0:00';
}

async function togglePreview(i) {
  const url = songPreviewUrls[i];
  if (!url) return;
  const btn = document.getElementById(`play-${i}`);

  if (activePreviewIdx === i) {
    if (songAudio.paused) {
      await songAudio.play().catch(() => {});
      btn.innerHTML = SVG_PAUSE;
    } else {
      songAudio.pause();
      btn.innerHTML = SVG_PLAY;
    }
    return;
  }

  if (activePreviewIdx !== null) resetPreviewRow(activePreviewIdx);

  activePreviewIdx = i;
  songAudio.src = url;
  songAudio.volume = previewVolLevel;
  songAudio.load();
  try {
    await songAudio.play();
    btn.innerHTML = SVG_PAUSE;
    document.getElementById(`ctrl-${i}`)?.classList.remove('d-none');
    document.getElementById(`vol-wrap-${i}`)?.classList.remove('d-none');
    const volEl = document.getElementById(`vol-${i}`);
    if (volEl) volEl.value = Math.round(previewVolLevel * 100);
  } catch {
    resetPreviewRow(i);
    activePreviewIdx = null;
  }
}

function seekPreview(i, e, bar) {
  if (activePreviewIdx !== i || !songAudio.duration) return;
  const rect = bar.getBoundingClientRect();
  songAudio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * songAudio.duration;
}

function setPreviewVol(val) {
  previewVolLevel = val / 100;
  songAudio.volume = previewVolLevel;
}

function fmtTime(s) {
  const m = Math.floor(s / 60);
  return `${m}:${Math.floor(s % 60).toString().padStart(2,'0')}`;
}

const SONG_PAGE_SIZE   = 15; // canciones por pestaña de página
const SONG_FETCH_LIMIT = 50; // máximo de resultados únicos que se piden a iTunes por búsqueda
let currentSongPage = 1;

function renderSongHitRow(i) {
  const hit = songHitsData[i];
  const year = hit.year ?? '—';
  const hasPreview = !!songPreviewUrls[i];
  const already = isInCatalog(hit.title, hit.artist);
  return `
  <div class="song-hit" id="hit-${i}">
    ${hit.art ? `<img src="${hit.art}" alt="">` : ''}
    <div class="song-hit-info">
      <div class="song-hit-title">${esc(hit.title)}</div>
      <div class="song-hit-sub">${esc(hit.artist)} · ${year}</div>
    </div>
    <button class="song-play-btn" id="play-${i}" onclick="togglePreview(${i})"
            ${hasPreview ? '' : 'disabled'} title="${hasPreview ? 'Escuchar preview (30s)' : 'Preview no disponible'}">
      ${SVG_PLAY}
    </button>
    <div class="song-player-ctrl d-none" id="ctrl-${i}">
      <div class="song-bar" id="bar-${i}" onclick="seekPreview(${i}, event, this)">
        <div class="song-bar-fill" id="fill-${i}"></div>
      </div>
      <span class="song-time" id="time-${i}">0:00</span>
    </div>
    <div class="song-vol-wrap d-none" id="vol-wrap-${i}">
      <svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
      <input type="range" class="song-vol" id="vol-${i}" min="0" max="100" value="70" oninput="setPreviewVol(this.value)">
    </div>
    <div id="add-wrap-${i}">
      ${already
        ? `<span class="small" style="color:var(--muted)">✓ En catálogo</span>`
        : `<button class="btn btn-sm btn-game rounded-pill px-3" style="font-size:.78rem" onclick="showGenrePicker(${i})">+ Añadir</button>`}
    </div>
  </div>`;
}

async function searchSongs() {
  const term = document.getElementById('song-search-input').value.trim();
  const box  = document.getElementById('song-results');
  const status = document.getElementById('song-search-status');
  if (!term) { status.textContent = 'Escribe un título o artista para buscar.'; box.style.display = 'none'; return; }

  songAudio.pause();
  activePreviewIdx = null;
  status.textContent = 'Buscando…';
  box.style.display = 'none';
  document.getElementById('song-pages').innerHTML = '';

  try {
    await Promise.all([
      loadCatalog(), // refresca allCatalogSongs para saber qué ya está añadido
      loadGenres(),  // refresca SONG_GENRES por si se añadió/renombró alguno
    ]);

    const res = await fetch(`https://itunes.apple.com/search?term=${encodeURIComponent(term)}&media=music&entity=song&limit=${SONG_FETCH_LIMIT}`);
    const data = await res.json();
    const raw = data.results || [];

    // La API de iTunes puede devolver la misma canción más de una vez (distintas ediciones/álbumes)
    const seen = new Set();
    const hits = raw.filter(t => {
      const key = `${t.trackName}|${t.artistName}`.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });

    if (!hits.length) {
      status.textContent = 'Sin resultados.';
      return;
    }

    songPreviewUrls = hits.map(t => t.previewUrl || null);
    songHitsData = hits.map(t => ({
      title:  t.trackName,
      artist: t.artistName,
      year:   t.releaseDate ? new Date(t.releaseDate).getFullYear() : null,
      art:    (t.artworkUrl60 || t.artworkUrl100 || '').replace('60x60', '80x80'),
    }));

    currentSongPage = 1;
    renderSongPager();
    goToSongPage(1);
    status.textContent = `${songHitsData.length} resultados`;
  } catch {
    status.textContent = 'Error al buscar. Inténtalo de nuevo.';
  }
}

function goToSongPage(page) {
  currentSongPage = page;
  const start = (page - 1) * SONG_PAGE_SIZE;
  const end   = Math.min(start + SONG_PAGE_SIZE, songHitsData.length);

  const box = document.getElementById('song-results');
  let html = '';
  for (let i = start; i < end; i++) html += renderSongHitRow(i);
  box.innerHTML = html;
  box.style.display = 'block';

  document.querySelectorAll('#song-pages button').forEach(btn => {
    btn.classList.toggle('btn-game', +btn.dataset.page === page);
    btn.classList.toggle('btn-outline-secondary', +btn.dataset.page !== page);
  });
}

function renderSongPager() {
  const pager = document.getElementById('song-pages');
  const totalPages = Math.ceil(songHitsData.length / SONG_PAGE_SIZE);
  if (totalPages <= 1) { pager.innerHTML = ''; return; }

  let html = '';
  for (let p = 1; p <= totalPages; p++) {
    html += `<button type="button" class="btn btn-sm rounded-pill" style="width:34px" data-page="${p}" onclick="goToSongPage(${p})">${p}</button>`;
  }
  pager.innerHTML = html;
}

function showGenrePicker(i) {
  const wrap = document.getElementById(`add-wrap-${i}`);
  const year = songHitsData[i]?.year;
  if (!year) { alert('No se pudo determinar el año de esta canción.'); return; }

  wrap.innerHTML = `
    <select class="form-select form-select-sm" style="font-size:.78rem;width:auto" onchange="addSongFromHit(${i}, this.value)">
      <option value="" selected disabled>Elegir género…</option>
      ${SONG_GENRES.map(g => `<option value="${esc(g)}">${esc(g)}</option>`).join('')}
    </select>
  `;
}

async function addSongFromHit(i, genre) {
  if (!genre) return;
  const wrap = document.getElementById(`add-wrap-${i}`);
  const { title, artist, year, art } = songHitsData[i];

  wrap.innerHTML = `<span class="small text-secondary">Añadiendo…</span>`;

  const r = await fetch(`${API}?action=add_song`, {
    method: 'POST',
    body: new URLSearchParams({ title, artist, year, genre, artwork_url: art || '' }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    wrap.innerHTML = `<span class="small" style="color:var(--muted)">✓ Añadida en ${esc(genre)}</span>`;
    loadCatalog();
  } else {
    wrap.innerHTML = `
      <button class="btn btn-sm btn-game rounded-pill px-3" style="font-size:.78rem" onclick="showGenrePicker(${i})">
        + Añadir
      </button>`;
    alert(r.error || 'Error al añadir la canción');
  }
}

function isInCatalog(title, artist) {
  const t = title.trim().toLowerCase();
  const a = artist.trim().toLowerCase();
  return allCatalogSongs.some(s => s.title.trim().toLowerCase() === t && s.artist.trim().toLowerCase() === a);
}

/* ── Catálogo actual (listado + borrado) ── */

async function loadCatalog() {
  const box = document.getElementById('catalog-list');
  const r = await fetch(`${API}?action=get_songs`).then(r => r.json()).catch(() => null);
  if (!Array.isArray(r)) {
    box.innerHTML = '<div class="text-center py-4 text-secondary">Error al cargar el catálogo</div>';
    return;
  }
  allCatalogSongs = r;
  renderCatalog(allCatalogSongs);
}

function filterCatalog() {
  const q = document.getElementById('catalog-search').value.toLowerCase();
  const filtered = allCatalogSongs.filter(s =>
    s.title.toLowerCase().includes(q) ||
    s.artist.toLowerCase().includes(q) ||
    (s.genre || '').toLowerCase().includes(q)
  );
  renderCatalog(filtered, !!q); // con texto de búsqueda, despliega los grupos que coinciden
}

function renderCatalog(songs, expand) {
  document.getElementById('catalog-count').textContent = `${songs.length} canciones`;
  const box = document.getElementById('catalog-list');
  if (!songs.length) {
    box.innerHTML = '<div class="text-center py-4 text-secondary">Sin canciones</div>';
    return;
  }

  const byGenre = {};
  songs.forEach(s => { (byGenre[s.genre || 'Sin género'] ??= []).push(s); });
  const genreNames = Object.keys(byGenre).sort();

  box.innerHTML = genreNames.map((genre, idx) => {
    const list = byGenre[genre];
    const panelId = `genre-panel-${idx}`;
    return `
    <div class="genre-group">
      <button class="genre-group-header w-100 border-0 bg-transparent text-start${expand ? '' : ' collapsed'}" type="button"
              data-bs-toggle="collapse" data-bs-target="#${panelId}" aria-expanded="${expand ? 'true' : 'false'}">
        <span>${esc(genre)}<span class="genre-group-count">${list.length}</span></span>
        <span class="chevron">▾</span>
      </button>
      <div class="collapse${expand ? ' show' : ''}" id="${panelId}">
        ${list.map(s => `
          <div class="catalog-row" id="catalog-row-${s.id}">
            <div class="catalog-row-info">
              <div class="catalog-row-title">${esc(s.title)}</div>
              <div class="catalog-row-sub">${esc(s.artist)} · ${s.year}</div>
            </div>
            <button class="catalog-del-btn" onclick="deleteCatalogSong(${s.id})" title="Eliminar del catálogo">✕</button>
          </div>
        `).join('')}
      </div>
    </div>`;
  }).join('');
}

async function deleteCatalogSong(id) {
  if (!confirm('¿Eliminar esta canción del catálogo? Esta acción no se puede deshacer.')) return;

  const row = document.getElementById(`catalog-row-${id}`);
  const btn = row?.querySelector('.catalog-del-btn');
  if (btn) btn.disabled = true;

  const r = await fetch(`${API}?action=delete_song`, {
    method: 'POST',
    body: new URLSearchParams({ id }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    allCatalogSongs = allCatalogSongs.filter(s => s.id !== id);
    filterCatalog();
  } else {
    if (btn) btn.disabled = false;
    alert(r.error || 'Error al eliminar la canción');
  }
}

/* ── Gestión de géneros ── */

async function loadGenres() {
  const r = await fetch(`${API}?action=list_genres`).then(r => r.json()).catch(() => null);
  if (!r?.success) {
    document.getElementById('genre-list').innerHTML = '<div class="text-center py-4 text-secondary">Error al cargar los géneros</div>';
    return;
  }
  allGenres = r.genres;
  SONG_GENRES = allGenres.map(g => g.name);
  renderGenreList();

  const memeSel = document.getElementById('meme-genre-select');
  if (memeSel) memeSel.innerHTML = SONG_GENRES.map(g => `<option value="${esc(g)}">${esc(g)}</option>`).join('');
}

function renderGenreList() {
  const box = document.getElementById('genre-list');
  if (!allGenres.length) {
    box.innerHTML = '<div class="text-center py-4 text-secondary">Sin géneros</div>';
    return;
  }
  box.innerHTML = allGenres.map(g => `
    <div class="catalog-row" id="genre-row-${g.id}">
      <div class="catalog-row-info">
        <div class="catalog-row-title">${esc(g.name)}</div>
      </div>
      <button class="catalog-del-btn" onclick="editGenre(${g.id})" title="Renombrar">✎</button>
    </div>
  `).join('');
}

function editGenre(id) {
  const genre = allGenres.find(g => g.id === id);
  if (!genre) return;
  const row = document.getElementById(`genre-row-${id}`);
  row.innerHTML = `
    <input type="text" class="search-bar" id="genre-edit-input-${id}" value="${esc(genre.name)}" style="flex:1"
           onkeydown="if(event.key==='Enter'){event.preventDefault();saveGenre(${id});}">
    <button class="btn btn-sm btn-game rounded-pill px-3" style="font-size:.78rem" onclick="saveGenre(${id})">Guardar</button>
  `;
  document.getElementById(`genre-edit-input-${id}`).focus();
}

async function saveGenre(id) {
  const input = document.getElementById(`genre-edit-input-${id}`);
  const name = input.value.trim();
  if (!name) { alert('El nombre no puede estar vacío'); return; }

  const r = await fetch(`${API}?action=rename_genre`, {
    method: 'POST',
    body: new URLSearchParams({ id, name }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    await loadGenres();
    await loadCatalog(); // refleja el nuevo nombre en las canciones ya asignadas a ese género
  } else {
    alert(r.error || 'Error al renombrar el género');
  }
}

async function addGenre() {
  const input = document.getElementById('new-genre-input');
  const name = input.value.trim();
  if (!name) { alert('Escribe un nombre para el género'); return; }

  const r = await fetch(`${API}?action=add_genre`, {
    method: 'POST',
    body: new URLSearchParams({ name }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    input.value = '';
    await loadGenres();
  } else {
    alert(r.error || 'Error al añadir el género');
  }
}

/* ── Catálogo de memes ── */

async function uploadMeme() {
  const fileInput = document.getElementById('meme-image-input');
  const year      = document.getElementById('meme-year-input').value;
  const genre     = document.getElementById('meme-genre-select').value;
  const title     = document.getElementById('meme-title-input').value.trim();
  const status    = document.getElementById('meme-upload-status');

  if (!fileInput.files.length) { status.textContent = 'Selecciona una imagen.'; return; }
  if (!year) { status.textContent = 'Indica el año.'; return; }

  status.textContent = 'Subiendo…';

  const body = new FormData();
  body.append('image', fileInput.files[0]);
  body.append('year', year);
  body.append('genre', genre);
  body.append('title', title);

  const r = await fetch(`${API}?action=add_meme`, { method: 'POST', body })
    .then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    status.textContent = '✓ Meme añadido al catálogo';
    document.getElementById('meme-upload-form').reset();
    loadMemeCatalog();
  } else {
    status.textContent = r.error || 'Error al subir el meme';
  }
}

async function loadMemeCatalog() {
  const box = document.getElementById('meme-catalog-list');
  const r = await fetch(`${API}?action=get_memes`).then(r => r.json()).catch(() => null);
  if (!Array.isArray(r)) {
    box.innerHTML = '<div class="text-center py-4 text-secondary">Error al cargar el catálogo</div>';
    return;
  }
  allMemeCatalog = r;
  renderMemeCatalog(allMemeCatalog, false);
}

function filterMemeCatalog() {
  const q = document.getElementById('meme-catalog-search').value.toLowerCase();
  const filtered = allMemeCatalog.filter(m =>
    (m.title || '').toLowerCase().includes(q) ||
    (m.genre || '').toLowerCase().includes(q)
  );
  renderMemeCatalog(filtered, !!q);
}

function renderMemeCatalog(memes, expand) {
  document.getElementById('meme-catalog-count').textContent = `${memes.length} memes`;
  const box = document.getElementById('meme-catalog-list');
  if (!memes.length) {
    box.innerHTML = '<div class="text-center py-4 text-secondary">Sin memes</div>';
    return;
  }

  const byGenre = {};
  memes.forEach(m => { (byGenre[m.genre || 'Sin género'] ??= []).push(m); });
  const genreNames = Object.keys(byGenre).sort();

  box.innerHTML = genreNames.map((genre, idx) => {
    const list = byGenre[genre];
    const panelId = `meme-genre-panel-${idx}`;
    return `
    <div class="genre-group">
      <button class="genre-group-header w-100 border-0 bg-transparent text-start${expand ? '' : ' collapsed'}" type="button"
              data-bs-toggle="collapse" data-bs-target="#${panelId}" aria-expanded="${expand ? 'true' : 'false'}">
        <span>${esc(genre)}<span class="genre-group-count">${list.length}</span></span>
        <span class="chevron">▾</span>
      </button>
      <div class="collapse${expand ? ' show' : ''}" id="${panelId}">
        ${list.map(m => `
          <div class="catalog-row" id="meme-row-${m.id}">
            <img src="${MEME_IMG_BASE}${m.image_url}" alt="Meme">
            <div class="catalog-row-info">
              <div class="catalog-row-title">${esc(m.title || '(sin título)')}</div>
              <div class="catalog-row-sub">${m.year}</div>
            </div>
            <button class="catalog-del-btn" onclick="deleteCatalogMeme(${m.id})" title="Eliminar del catálogo">✕</button>
          </div>
        `).join('')}
      </div>
    </div>`;
  }).join('');
}

async function deleteCatalogMeme(id) {
  if (!confirm('¿Eliminar este meme del catálogo? Esta acción no se puede deshacer.')) return;

  const row = document.getElementById(`meme-row-${id}`);
  const btn = row?.querySelector('.catalog-del-btn');
  if (btn) btn.disabled = true;

  const r = await fetch(`${API}?action=delete_meme`, {
    method: 'POST',
    body: new URLSearchParams({ id }),
  }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

  if (r.success) {
    allMemeCatalog = allMemeCatalog.filter(m => m.id !== id);
    filterMemeCatalog();
  } else {
    if (btn) btn.disabled = false;
    alert(r.error || 'Error al eliminar el meme');
  }
}

</script>
</body>
</html>
