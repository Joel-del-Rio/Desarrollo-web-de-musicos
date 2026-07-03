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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=6">
  <style>
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
        <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="saResetPoints()" style="font-size:.78rem">
          🗑️ Reiniciar puntos
        </button>
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
let allGames = [];
let detailModal;

document.addEventListener('DOMContentLoaded', () => {
  detailModal = new bootstrap.Modal(document.getElementById('gameDetailModal'));
  // Si ya hemos autenticado en esta sesión, saltamos el login
  if (sessionStorage.getItem('sa_auth') === '1') showPanel();
});

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

async function saResetPoints() {
  if (!confirm('¿Seguro? Esto pondrá a 0 los puntos de TODOS los jugadores en el ranking global.')) return;
  const r = await fetch(`${API}?action=superadmin_reset_points`, { method: 'POST' }).then(r => r.json()).catch(() => ({}));
  if (r.success) {
    alert('Puntos reiniciados correctamente.');
    loadStats();
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}
</script>
</body>
</html>
