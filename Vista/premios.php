<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Premios</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>

<div class="container py-4" style="max-width:520px">

  <div class="text-center mb-4">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="max-width:180px">
  </div>

  <!-- Formulario PIN -->
  <div id="pin-form">
    <div class="card p-4 mb-3">
      <h5 class="fw-black mb-1">🏆 Premios de la partida</h5>
      <p class="small mb-3" style="color:var(--muted)">Introduce el PIN de la partida para ver los premios y la clasificación final.</p>
      <input id="pin-input" type="text" class="form-control form-control-lg text-center fw-black mb-3"
             inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="· · · ·"
             style="font-size:2rem;letter-spacing:.3em" autocomplete="off">
      <button class="btn btn-game w-100 rounded-pill fw-bold" onclick="lookupGame()">Ver premios →</button>
      <div id="pin-error" class="alert alert-danger mt-3 py-2 small d-none text-center"></div>
    </div>
  </div>

  <!-- Contenido de premios (oculto hasta buscar) -->
  <div id="prizes-content" class="d-none d-flex flex-column gap-3">

    <!-- Estado de la partida -->
    <div id="game-status-bar" class="text-center small fw-semibold py-2 px-3 rounded-3" style="background:rgba(255,255,255,.06)"></div>

    <!-- Podio de premios -->
    <div id="prizes-panel" class="d-none card p-4">
      <div class="text-secondary small text-uppercase fw-semibold mb-3">Premios</div>
      <div class="d-flex flex-column gap-3" id="prizes-list"></div>
    </div>

    <!-- Clasificación -->
    <div class="card p-4">
      <div class="text-secondary small text-uppercase fw-semibold mb-3">Clasificación</div>
      <div id="leaderboard-list"></div>
      <div id="lb-live" class="text-center small mt-2 d-none" style="color:var(--muted)">
        Actualizando en tiempo real…
      </div>
    </div>

    <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="resetView()">‹ Buscar otra partida</button>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';
let currentGameId = null;
let liveTimer     = null;

// Pre-rellenar PIN desde URL
const urlPin = new URLSearchParams(window.location.search).get('pin');
if (urlPin) {
  document.getElementById('pin-input').value = urlPin.replace(/\D/g,'').slice(0,4);
  window.addEventListener('load', lookupGame);
}

async function lookupGame() {
  const pin   = document.getElementById('pin-input').value.trim();
  const errEl = document.getElementById('pin-error');
  errEl.classList.add('d-none');
  if (pin.length !== 4) { errEl.textContent = 'El PIN debe tener 4 dígitos'; errEl.classList.remove('d-none'); return; }

  try {
    const r    = await fetch(`${API}?action=game_state_by_pin&pin=${encodeURIComponent(pin)}&_t=${Date.now()}`, { cache: 'no-store' });
    const data = await r.json();
    if (data.error) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }
    currentGameId = data.id;
    renderPrizesView(data);
  } catch {
    errEl.textContent = 'Error de conexión';
    errEl.classList.remove('d-none');
  }
}

function renderPrizesView(data) {
  document.getElementById('pin-form').classList.add('d-none');
  document.getElementById('prizes-content').classList.remove('d-none');

  const statusBar = document.getElementById('game-status-bar');
  const labels = { waiting: '⏳ Esperando jugadores', question: '🎵 Partida en curso', results: '📊 Viendo resultados', finished: '🏁 Partida finalizada' };
  statusBar.textContent = labels[data.status] || data.status;

  renderPrizes(data);
  renderLeaderboard(data.players || [], data);

  if (data.status !== 'finished') {
    document.getElementById('lb-live').classList.remove('d-none');
    startLive();
  } else {
    stopLive();
  }
}

function renderPrizes(data) {
  const prizes = [data.prize_1, data.prize_2, data.prize_3].filter(Boolean);
  const panel  = document.getElementById('prizes-panel');
  const list   = document.getElementById('prizes-list');
  if (!prizes.length) { panel.classList.add('d-none'); return; }

  panel.classList.remove('d-none');
  const icons  = ['🥇','🥈','🥉'];
  const labels = ['1er lugar', '2do lugar', '3er lugar'];
  const players = data.players || [];

  list.innerHTML = [data.prize_1, data.prize_2, data.prize_3].map((prize, i) => {
    if (!prize) return '';
    const winner = data.status === 'finished' ? (players[i] ? `<span class="fw-semibold" style="color:var(--accent)">${esc(players[i].name)}</span>` : '—') : '<span style="color:var(--muted)">Por decidir</span>';
    return `<div class="d-flex align-items-center gap-3 py-2 border-bottom border-secondary border-opacity-25">
      <span style="font-size:2rem">${icons[i]}</span>
      <div style="flex:1">
        <div class="small text-secondary fw-semibold">${labels[i]}</div>
        <div class="fw-bold">${esc(prize)}</div>
      </div>
      <div class="text-end">${winner}</div>
    </div>`;
  }).join('');
}

function renderLeaderboard(players, data) {
  const el = document.getElementById('leaderboard-list');
  if (!players.length) { el.innerHTML = '<div class="text-secondary small text-center py-3">Sin jugadores aún</div>'; return; }
  el.innerHTML = players.map((p, i) => `
    <div class="lb-row">
      <span class="lb-rank">${['🥇','🥈','🥉'][i] ?? (i+1) + '.'}</span>
      <span class="avatar-circle" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</span>
      <span class="lb-name">${esc(p.name)}</span>
      <span class="lb-score">${p.score} pts</span>
    </div>`).join('');
}

function startLive() {
  stopLive();
  liveTimer = setInterval(async () => {
    try {
      const r    = await fetch(`${API}?action=game_state&game_id=${currentGameId}&_t=${Date.now()}`, { cache: 'no-store' });
      const data = await r.json();
      if (data.error) return;
      renderLeaderboard(data.players || [], data);
      const labels = { waiting: '⏳ Esperando jugadores', question: '🎵 Partida en curso', results: '📊 Viendo resultados', finished: '🏁 Partida finalizada' };
      document.getElementById('game-status-bar').textContent = labels[data.status] || data.status;
      if (data.status === 'finished') { stopLive(); document.getElementById('lb-live').classList.add('d-none'); renderPrizes(data); }
    } catch {}
  }, 3000);
}
function stopLive() { clearInterval(liveTimer); liveTimer = null; }

function resetView() {
  stopLive();
  currentGameId = null;
  document.getElementById('prizes-content').classList.add('d-none');
  document.getElementById('pin-form').classList.remove('d-none');
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>

<div class="text-center mt-4 pb-3">
  <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Inicio</a>
</div>

</body>
</html>
