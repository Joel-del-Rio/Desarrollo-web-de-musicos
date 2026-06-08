/* ── Estado ── */
let gameId     = localStorage.getItem(GK);
let adminToken = localStorage.getItem(TK);
let pollTimer  = null;
let lastStatus = null;

/* ── Arranque ── */
(function init() {
  if (gameId && adminToken) {
    // Intentar retomar una partida existente
    fetchState().then(state => {
      if (state && !state.error) {
        applyState(state);
      } else {
        clearSession();
        showScreen('setup');
      }
    });
  } else {
    showScreen('setup');
  }
})();

/* ── API helpers ── */
async function api(action, body = {}) {
  const isGet = Object.keys(body).length === 0;
  let url = `${API}?action=${action}`;
  let opts = {};
  if (isGet) {
    url += `&game_id=${gameId}`;
  } else {
    opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
             body: new URLSearchParams({ game_id: gameId, admin_token: adminToken, ...body }) };
  }
  const res = await fetch(url);
  return res.json();
}

async function fetchState() {
  try { return await api('game_state'); }
  catch { return null; }
}

/* ── Polling ── */
function startPolling(ms = 1500) {
  stopPolling();
  pollTimer = setInterval(async () => {
    const state = await fetchState();
    if (state && !state.error) applyState(state);
  }, ms);
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

/* ── Pantallas ── */
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(`screen-${name}`).classList.add('active');
}

/* ── Aplicar estado recibido ── */
function applyState(state) {
  if (state.status === lastStatus && state.status !== 'question') return;
  lastStatus = state.status;

  switch (state.status) {
    case 'waiting':  renderWaiting(state);   break;
    case 'question': renderQuestion(state);  break;
    case 'results':  renderResults(state);   break;
    case 'finished': renderFinished(state);  break;
  }
}

/* ── Render: Waiting ── */
function renderWaiting(state) {
  showScreen('waiting');
  document.getElementById('w-pin').textContent   = localStorage.getItem(GK + '_pin') || '----';
  document.getElementById('w-count').textContent = state.player_count || 0;

  const list = document.getElementById('w-players');
  list.innerHTML = '';
  (state.players || []).forEach(p => {
    const chip = document.createElement('div');
    chip.className = 'player-chip';
    chip.style.background = p.avatar_color + '33';
    chip.style.border = `2px solid ${p.avatar_color}`;
    chip.innerHTML = `
      <span class="avatar" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</span>
      <span style="color:var(--text)">${esc(p.name)}</span>
    `;
    list.appendChild(chip);
  });

  const btn = document.getElementById('btn-start');
  btn.disabled = (state.player_count || 0) < 1;

  startPolling(1500);
}

/* ── Render: Question ── */
function renderQuestion(state) {
  showScreen('question');
  const song    = state.song || {};
  const players = state.players || [];

  document.getElementById('q-round').textContent  = state.current_round;
  document.getElementById('q-total').textContent  = state.total_rounds;
  document.getElementById('q-title').textContent  = song.title  || '—';
  document.getElementById('q-artist').textContent = song.artist || '—';
  document.getElementById('q-players').textContent  = players.length;
  document.getElementById('q-answered').textContent = state.answer_count || 0;

  const pct = players.length > 0
    ? Math.round(((state.answer_count || 0) / players.length) * 100)
    : 0;
  document.getElementById('q-progress').style.width = pct + '%';

  // Timer visual
  updateTimer(state.time_left ?? 20);

  // Leaderboard lateral
  renderLeaderboard('q-leaderboard', players);

  startPolling(1000);
}

/* ── Render: Results ── */
function renderResults(state) {
  showScreen('results');
  const song    = state.song    || {};
  const players = state.players || [];

  document.getElementById('r-round').textContent  = state.current_round;
  document.getElementById('r-total').textContent  = state.total_rounds;
  document.getElementById('r-title').textContent  = song.title  || '—';
  document.getElementById('r-artist').textContent = song.artist || '—';
  document.getElementById('r-year').textContent   = song.year   ? `📅 ${song.year}` : '—';
  document.getElementById('r-genre').textContent  = song.genre  || '';

  // Respuestas
  const list = document.getElementById('r-results');
  list.innerHTML = '';
  (state.round_results || []).forEach(r => {
    const row = document.createElement('div');
    row.className = `res-row ${r.is_correct ? 'res-correct' : 'res-wrong'}`;
    row.innerHTML = `
      <span style="font-size:1.25rem">${r.is_correct ? '✅' : '❌'}</span>
      <span class="avatar" style="background:${r.avatar_color};width:28px;height:28px;border-radius:50%;
            display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:900;color:#fff">
        ${r.name[0].toUpperCase()}
      </span>
      <span style="flex:1;font-weight:600">${esc(r.name)}</span>
      <span style="color:var(--muted)">${r.year_guess}</span>
      <span style="font-weight:800;color:var(--accent);min-width:60px;text-align:right">+${r.points_earned} pts</span>
    `;
    list.appendChild(row);
  });

  // Botón de siguiente
  const isLast = state.current_round >= state.total_rounds;
  const btnNext = document.getElementById('btn-next');
  btnNext.textContent = isLast ? '🏆 Ver Resultados Finales' : 'Siguiente Ronda →';

  renderLeaderboard('r-leaderboard', players);
  startPolling(2000);
}

/* ── Render: Finished ── */
function renderFinished(state) {
  stopPolling();
  showScreen('finished');
  const players = state.players || [];

  // Podio
  renderPodium('f-podium', players);
  renderLeaderboard('f-leaderboard', players);
}

/* ── Helpers de render ── */
function renderLeaderboard(containerId, players) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = '';
  players.forEach((p, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row';
    const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i + 1}.`;
    row.innerHTML = `
      <span class="lb-rank">${medal}</span>
      <span class="avatar" style="background:${p.avatar_color};width:28px;height:28px;border-radius:50%;
            display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;color:#fff">
        ${p.name[0].toUpperCase()}
      </span>
      <span class="lb-name">${esc(p.name)}</span>
      <span class="lb-score">${p.score} pts</span>
    `;
    el.appendChild(row);
  });
}

function renderPodium(containerId, players) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = '';

  const top3 = players.slice(0, 3);
  // Orden visual: 2º, 1º, 3º
  const order = [top3[1], top3[0], top3[2]].filter(Boolean);
  const classes = top3[1] ? ['podium-2nd', 'podium-1st', 'podium-3rd'] : ['podium-1st', 'podium-3rd'];
  const medals  = ['🥈', '🥇', '🥉'];

  order.forEach((p, i) => {
    const step = document.createElement('div');
    step.className = `podium-step ${classes[i]}`;
    step.innerHTML = `
      <div class="podium-medal">${medals[i]}</div>
      <div class="podium-avatar" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</div>
      <div class="podium-name">${esc(p.name)}</div>
      <div class="podium-score">${p.score} pts</div>
      <div class="podium-bar"></div>
    `;
    el.appendChild(step);
  });
}

let timerInterval = null;
function updateTimer(seconds) {
  const circle = document.getElementById('timer-circle');
  const label  = document.getElementById('q-timer');
  if (!circle || !label) return;

  const circumference = 2 * Math.PI * 35; // r=35

  function tick(s) {
    label.textContent = Math.ceil(s);
    const offset = circumference * (1 - s / 20);
    circle.style.strokeDashoffset = offset;
    circle.style.stroke = s <= 5 ? '#e21b3c' : s <= 10 ? '#d89e00' : 'var(--accent)';
  }

  if (timerInterval) clearInterval(timerInterval);
  let s = seconds;
  tick(s);
  timerInterval = setInterval(() => {
    s -= 1;
    if (s < 0) { clearInterval(timerInterval); return; }
    tick(s);
  }, 1000);
}

/* ── Acciones admin ── */
async function createGame() {
  const rounds = parseInt(document.getElementById('rounds-input').value, 10);
  const errEl  = document.getElementById('setup-error');
  errEl.style.display = 'none';

  try {
    const data = await fetch(`${API}?action=create_game`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ total_rounds: rounds }),
    }).then(r => r.json());

    if (data.error) { errEl.textContent = data.error; errEl.style.display = 'block'; return; }

    gameId     = String(data.id);
    adminToken = data.admin_token;
    localStorage.setItem(GK, gameId);
    localStorage.setItem(TK, adminToken);
    localStorage.setItem(GK + '_pin', data.pin);

    const state = await fetchState();
    applyState(state);
  } catch (e) {
    errEl.textContent = 'Error de conexión. ¿Está encendido el servidor?';
    errEl.style.display = 'block';
  }
}

async function startGame() {
  const data = await api('start_game', {});
  if (!data.error) { const s = await fetchState(); applyState(s); }
}

async function showResults() {
  await api('show_results', {});
  const s = await fetchState(); applyState(s);
}

async function nextRound() {
  await api('next_round', {});
  const s = await fetchState(); applyState(s);
}

function newGame() {
  clearSession();
  showScreen('setup');
}

function clearSession() {
  localStorage.removeItem(GK);
  localStorage.removeItem(TK);
  localStorage.removeItem(GK + '_pin');
  gameId = adminToken = null;
  lastStatus = null;
  stopPolling();
}

function esc(str) {
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
