/* ── Estado ── */
let playerId    = parseInt(localStorage.getItem(PK), 10) || null;
let currentOpts = [];   // opciones de año del turno activo
let pollTimer   = null;
let lastStatus  = null;
let hasAnswered = false;

/* ── Arranque ── */
(function init() {
  if (playerId) {
    fetchState().then(state => {
      if (state && !state.error) {
        applyState(state);
      } else {
        clearSession();
        showScreen('join');
      }
    });
  } else {
    showScreen('join');
  }
  // PIN auto-format
  const pinEl = document.getElementById('pin-input');
  if (pinEl) {
    pinEl.addEventListener('input', () => {
      pinEl.value = pinEl.value.replace(/\D/g, '').slice(0, 4);
    });
    pinEl.addEventListener('keydown', e => { if (e.key === 'Enter') joinGame(); });
  }
  document.getElementById('name-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') joinGame();
  });
})();

/* ── API ── */
async function fetchState() {
  try {
    const r = await fetch(`${API}?action=player_state&player_id=${playerId}`);
    return r.json();
  } catch { return null; }
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
  document.getElementById(`screen-${name}`)?.classList.add('active');
}

/* ── Dispatch de estado ── */
function applyState(state) {
  const st = state.status;

  // Durante la pregunta, no re-renderizar si ya respondió (pantalla "answered")
  if (st === 'question' && state.has_answered) {
    if (lastStatus !== 'answered') { showScreen('answered'); lastStatus = 'answered'; }
    return;
  }

  if (st === lastStatus && st !== 'question') return;
  lastStatus = st;

  switch (st) {
    case 'waiting':  renderLobby(state);    break;
    case 'question': renderQuestion(state); break;
    case 'results':  renderResults(state);  break;
    case 'finished': renderFinished(state); break;
  }
}

/* ── Render: Lobby ── */
function renderLobby(state) {
  showScreen('lobby');
  const p = state.player || {};
  document.getElementById('lobby-name').textContent   = p.name || '—';
  document.getElementById('lobby-count').textContent  = state.total_players || 0;
  const av = document.getElementById('lobby-avatar');
  av.style.background = p.avatar_color || '#e94560';
  av.textContent = (p.name || '?')[0].toUpperCase();
  startPolling(1500);
}

/* ── Render: Pregunta ── */
function renderQuestion(state) {
  hasAnswered = false;
  showScreen('question');
  const song = state.song || {};

  document.getElementById('q-round').textContent  = state.current_round;
  document.getElementById('q-total').textContent  = state.total_rounds;
  document.getElementById('q-title').textContent  = song.title  || '—';
  document.getElementById('q-artist').textContent = song.artist || '—';

  // Opciones de año
  currentOpts = song.options || [];
  const colors = ['ans-a', 'ans-b', 'ans-c', 'ans-d'];
  currentOpts.forEach((year, i) => {
    const btn = document.getElementById(`btn-${i}`);
    if (!btn) return;
    btn.textContent = year;
    btn.disabled    = false;
    btn.className   = `answer-btn ${colors[i]}`;
  });

  // Timer visual
  startCountdown(state.time_left ?? 20);
  startPolling(1000);
}

/* ── Render: Resultados ── */
function renderResults(state) {
  stopCountdown();
  showScreen('results');
  const song   = state.song     || {};
  const myRes  = state.my_result || null;

  document.getElementById('r-title').textContent  = song.title  || '—';
  document.getElementById('r-artist').textContent = song.artist || '—';
  document.getElementById('r-year').textContent   = song.year   || '—';

  if (myRes) {
    const correct = !!myRes.is_correct;
    document.getElementById('res-icon').textContent = correct ? '✅' : '❌';
    document.getElementById('res-msg').textContent  = correct ? '¡Correcto!' : `Incorrecto — era ${song.year}`;
    document.getElementById('res-msg').style.color  = correct ? 'var(--success)' : 'var(--error)';
    document.getElementById('res-pts').textContent  = correct ? `+${myRes.points_earned} pts` : '0 pts';
  } else {
    document.getElementById('res-icon').textContent = '⏱️';
    document.getElementById('res-msg').textContent  = 'No respondiste a tiempo';
    document.getElementById('res-msg').style.color  = 'var(--muted)';
    document.getElementById('res-pts').textContent  = '0 pts';
  }

  // Ranking
  const p = state.player || {};
  document.getElementById('res-rank').textContent =
    `Posición ${state.player_rank} / ${state.total_players}  •  Total: ${p.score} pts`;

  // Mini leaderboard
  renderMiniLeaderboard('res-leaderboard', state.round_results || [], p.name);

  startPolling(2000);
}

/* ── Render: Finished ── */
function renderFinished(state) {
  stopPolling();
  showScreen('finished');
  const p = state.player || {};
  document.getElementById('f-rank').textContent  = `🏅 Posición final: ${state.player_rank} / ${state.total_players}`;
  document.getElementById('f-score').textContent = `${p.score} pts`;

  const board = document.getElementById('f-leaderboard');
  board.innerHTML = '';
  (state.leaderboard || []).forEach((pl, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row' + (pl.name === p.name ? ' me' : '');
    const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i + 1}.`;
    row.innerHTML = `
      <span class="lb-rank">${medal}</span>
      <span class="avatar" style="background:${pl.avatar_color};width:28px;height:28px;border-radius:50%;
            display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;color:#fff">
        ${pl.name[0].toUpperCase()}
      </span>
      <span class="lb-name">${esc(pl.name)}</span>
      <span class="lb-score">${pl.score} pts</span>
    `;
    board.appendChild(row);
  });
}

function renderMiniLeaderboard(id, results, myName) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = '';
  results.slice(0, 5).forEach((r, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row' + (r.name === myName ? ' me' : '');
    row.innerHTML = `
      <span class="lb-rank">${i + 1}.</span>
      <span class="lb-name">${esc(r.name)}</span>
      <span style="font-size:.8rem;color:var(--muted)">${r.year_guess}</span>
      <span class="lb-score">+${r.points_earned}</span>
    `;
    el.appendChild(row);
  });
}

/* ── Timer ── */
let countdown = null;
function startCountdown(seconds) {
  stopCountdown();
  let s = seconds;
  function tick() {
    const fill = document.getElementById('timer-fill');
    const secs = document.getElementById('q-secs');
    if (fill) fill.style.width = Math.max(0, (s / 20) * 100) + '%';
    if (secs) {
      secs.textContent = Math.ceil(s);
      secs.style.color = s <= 5 ? 'var(--error)' : s <= 10 ? '#d89e00' : 'var(--accent)';
    }
  }
  tick();
  countdown = setInterval(() => {
    s -= 1;
    if (s < 0) { stopCountdown(); return; }
    tick();
  }, 1000);
}
function stopCountdown() {
  if (countdown) { clearInterval(countdown); countdown = null; }
}

/* ── Acciones jugador ── */
async function joinGame() {
  const pin  = document.getElementById('pin-input').value.trim();
  const name = document.getElementById('name-input').value.trim();
  const errEl = document.getElementById('join-error');
  errEl.style.display = 'none';

  if (pin.length !== 4)    { showErr('El PIN debe tener 4 dígitos'); return; }
  if (!name)               { showErr('Escribe tu nombre'); return; }

  try {
    const data = await fetch(`${API}?action=join_game`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ pin, name }),
    }).then(r => r.json());

    if (data.error) { showErr(data.error); return; }

    playerId = data.player_id;
    localStorage.setItem(PK, playerId);
    localStorage.setItem(GK, data.game_id);

    const state = await fetchState();
    if (state && !state.error) applyState(state);
  } catch { showErr('Error de conexión'); }

  function showErr(msg) {
    errEl.textContent = msg; errEl.style.display = 'block';
  }
}

async function submitAnswer(index) {
  if (hasAnswered || !currentOpts[index]) return;
  hasAnswered = true;

  // Feedback visual inmediato
  for (let i = 0; i < 4; i++) {
    const b = document.getElementById(`btn-${i}`);
    if (b) { b.disabled = true; b.style.opacity = i === index ? '1' : '0.35'; }
  }

  try {
    await fetch(`${API}?action=submit_answer`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ player_id: playerId, year_guess: currentOpts[index] }),
    });
  } catch { /* silenciar errores de red */ }

  stopCountdown();
  showScreen('answered');
  lastStatus = 'answered';
}

function goToJoin() {
  clearSession();
  showScreen('join');
  document.getElementById('pin-input').value  = '';
  document.getElementById('name-input').value = '';
}

function clearSession() {
  localStorage.removeItem(PK);
  localStorage.removeItem(GK);
  playerId = null; lastStatus = null; hasAnswered = false;
  stopPolling(); stopCountdown();
}

function esc(str) {
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
