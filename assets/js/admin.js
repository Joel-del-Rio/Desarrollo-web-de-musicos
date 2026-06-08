/* ── Estado ── */
let gameId     = localStorage.getItem(GK);
let adminToken = localStorage.getItem(TK);
let pollTimer  = null;
let timerInterval = null;
let lastStatus = null;
let questionTime = 30;

/* ── Arranque ── */
(function init() {
  if (gameId && adminToken) {
    fetchState().then(state => {
      if (state && !state.error) applyState(state);
      else { clearSession(); showScreen('setup'); }
    });
  } else {
    showScreen('setup');
  }
})();

/* ── API ── */
async function apiGet(action) {
  const r = await fetch(`${API}?action=${action}&game_id=${gameId}`);
  return r.json();
}
async function apiPost(action, extra = {}) {
  const r = await fetch(`${API}?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ game_id: gameId, admin_token: adminToken, ...extra }),
  });
  return r.json();
}
async function fetchState() {
  try { return await apiGet('game_state'); } catch { return null; }
}

/* ── Polling ── */
function startPolling(ms = 1500) {
  stopPolling();
  pollTimer = setInterval(async () => {
    const s = await fetchState();
    if (s && !s.error) applyState(s);
  }, ms);
}
function stopPolling() { clearInterval(pollTimer); pollTimer = null; }

/* ── Pantallas ── */
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(`screen-${name}`).classList.add('active');
}

/* ── Dispatcher ── */
function applyState(state) {
  const st = state.status;
  // waiting siempre re-renderiza (lista de jugadores cambia)
  if (st === lastStatus && st !== 'waiting' && st !== 'question') return;
  lastStatus = st;
  switch (st) {
    case 'waiting':  renderWaiting(state);  break;
    case 'question': renderQuestion(state); break;
    case 'results':  renderResults(state);  break;
    case 'finished': renderFinished(state); break;
  }
}

/* ── Waiting ── */
function renderWaiting(state) {
  showScreen('waiting');
  document.getElementById('w-pin').textContent   = localStorage.getItem(GK + '_pin') || '----';
  document.getElementById('w-count').textContent = state.player_count || 0;

  const list = document.getElementById('w-players');
  list.innerHTML = '';
  (state.players || []).forEach(p => {
    const chip = document.createElement('div');
    chip.className = 'player-chip';
    chip.style.cssText = `background:${p.avatar_color}22;border:2px solid ${p.avatar_color}`;
    chip.innerHTML = `
      <span class="avatar-circle" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</span>
      <span>${esc(p.name)}</span>`;
    list.appendChild(chip);
  });

  document.getElementById('btn-start').disabled = (state.player_count || 0) < 1;
  startPolling(1500);
}

/* ── Question ── */
function renderQuestion(state) {
  showScreen('question');
  const song    = state.song    || {};
  const players = state.players || [];
  questionTime  = state.question_time || 30;

  document.getElementById('q-round').textContent  = state.current_round;
  document.getElementById('q-total').textContent  = state.total_rounds;
  document.getElementById('q-title').textContent  = song.title  || '—';
  document.getElementById('q-artist').textContent = song.artist || '—';
  document.getElementById('q-year').textContent   = song.year   || '—';
  document.getElementById('q-genre').textContent  = song.genre  || '';
  document.getElementById('q-players').textContent  = players.length;
  document.getElementById('q-answered').textContent = state.answer_count || 0;

  const pct = players.length > 0
    ? Math.round(((state.answer_count || 0) / players.length) * 100) : 0;
  document.getElementById('q-progress').style.width = pct + '%';

  startTimerRing(state.time_left ?? questionTime, questionTime);
  renderLeaderboard('q-leaderboard', players);
  startPolling(1000);
}

/* ── Results ── */
function renderResults(state) {
  stopTimer();
  showScreen('results');
  const song    = state.song    || {};
  const players = state.players || [];

  document.getElementById('r-round').textContent  = state.current_round;
  document.getElementById('r-total').textContent  = state.total_rounds;
  document.getElementById('r-title').textContent  = song.title  || '—';
  document.getElementById('r-artist').textContent = song.artist || '—';
  document.getElementById('r-year').textContent   = song.year ? `📅 ${song.year}` : '—';
  document.getElementById('r-genre').textContent  = song.genre || '';

  // Lista de respuestas
  const list = document.getElementById('r-results');
  list.innerHTML = '';
  (state.round_results || []).forEach(r => {
    const row = document.createElement('div');
    row.className = `res-row ${r.is_correct ? 'res-correct' : 'res-wrong'}`;
    row.innerHTML = `
      <span style="font-size:1.1rem">${r.is_correct ? '✅' : '❌'}</span>
      <span class="avatar-circle" style="background:${r.avatar_color}">${r.name[0].toUpperCase()}</span>
      <span style="flex:1;font-weight:600">${esc(r.name)}</span>
      <span class="fw-bold" style="color:var(--accent)">+${r.points_earned} pts</span>`;
    list.appendChild(row);
  });

  const isLast = state.current_round >= state.total_rounds;
  document.getElementById('btn-next').textContent = isLast ? '🏆 Resultados finales' : 'Siguiente Ronda →';

  renderLeaderboard('r-leaderboard', players);
  startPolling(2000);
}

/* ── Finished ── */
function renderFinished(state) {
  stopPolling(); stopTimer();
  showScreen('finished');
  renderPodium('f-podium', state.players || []);
  renderLeaderboard('f-leaderboard', state.players || []);
}

/* ── Helpers render ── */
function renderLeaderboard(id, players) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = '';
  players.forEach((p, i) => {
    const medal = ['🥇','🥈','🥉'][i] ?? `${i+1}.`;
    const row = document.createElement('div');
    row.className = 'lb-row';
    row.innerHTML = `
      <span class="lb-rank">${medal}</span>
      <span class="avatar-circle" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</span>
      <span class="lb-name">${esc(p.name)}</span>
      <span class="lb-score">${p.score} pts</span>`;
    el.appendChild(row);
  });
}

function renderPodium(id, players) {
  const el = document.getElementById(id);
  if (!el) return;
  const top3   = players.slice(0, 3);
  const order  = [top3[1], top3[0], top3[2]].filter(Boolean);
  const cls    = ['podium-2nd','podium-1st','podium-3rd'];
  const medals = ['🥈','🥇','🥉'];
  el.innerHTML = '';
  order.forEach((p, i) => {
    const step = document.createElement('div');
    step.className = `podium-step ${cls[i]}`;
    step.innerHTML = `
      <div class="podium-medal" style="font-size:1.75rem">${medals[i]}</div>
      <div class="podium-avatar-big" style="background:${p.avatar_color}">${p.name[0].toUpperCase()}</div>
      <div style="font-size:.8rem;font-weight:700;max-width:72px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.name)}</div>
      <div style="font-size:.7rem;color:var(--muted)">${p.score} pts</div>
      <div class="podium-bar"></div>`;
    el.appendChild(step);
  });
}

/* ── Timer ring ── */
function startTimerRing(seconds, total) {
  stopTimer();
  const circle = document.getElementById('timer-circle');
  const label  = document.getElementById('q-timer');
  if (!circle || !label) return;
  const circ = 2 * Math.PI * 35;
  let s = seconds;
  function tick() {
    label.textContent = Math.ceil(s);
    circle.style.strokeDashoffset = circ * (1 - s / total);
    circle.style.stroke = s <= 5 ? '#e21b3c' : s <= total * 0.33 ? '#d89e00' : 'var(--accent)';
  }
  tick();
  timerInterval = setInterval(() => { s -= 1; if (s < 0) { stopTimer(); return; } tick(); }, 1000);
}
function stopTimer() { clearInterval(timerInterval); timerInterval = null; }

/* ── Acciones admin ── */
async function createGame() {
  const rounds = parseInt(document.getElementById('rounds-input').value, 10);
  const time   = parseInt(document.getElementById('time-input').value,   10);
  const errEl  = document.getElementById('setup-error');
  errEl.classList.add('d-none');
  try {
    const data = await fetch(`${API}?action=create_game`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ total_rounds: rounds, question_time: time }),
    }).then(r => r.json());

    if (data.error) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }

    gameId = String(data.id); adminToken = data.admin_token;
    localStorage.setItem(GK, gameId);
    localStorage.setItem(TK, adminToken);
    localStorage.setItem(GK + '_pin', data.pin);

    const s = await fetchState(); applyState(s);
  } catch {
    errEl.textContent = 'Error de conexión. ¿Está XAMPP encendido?';
    errEl.classList.remove('d-none');
  }
}

async function startGame() {
  const d = await apiPost('start_game');
  if (d.error) { alert(d.error); return; }
  const s = await fetchState(); applyState(s);
}
async function showResults() {
  await apiPost('show_results');
  const s = await fetchState(); applyState(s);
}
async function nextRound() {
  await apiPost('next_round');
  const s = await fetchState(); applyState(s);
}
function newGame() { clearSession(); showScreen('setup'); }

function clearSession() {
  localStorage.removeItem(GK); localStorage.removeItem(TK); localStorage.removeItem(GK+'_pin');
  gameId = adminToken = null; lastStatus = null; stopPolling(); stopTimer();
}
function esc(s) {
  return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
