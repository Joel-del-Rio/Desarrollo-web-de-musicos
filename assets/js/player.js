/* ── Estado ── */
let playerId     = parseInt(localStorage.getItem(PK), 10) || null;
let pollTimer    = null;
let countdown    = null;
let lastStatus   = null;
let selectedPos  = null;   // posición seleccionada en el timeline
let currentSong  = null;   // canción de la ronda actual
let questionTime = 30;

/* ── Arranque ── */
(function init() {
  if (playerId) {
    fetchState().then(state => {
      if (state && !state.error) applyState(state);
      else { clearSession(); showScreen('join'); }
    });
  } else {
    showScreen('join');
  }
  document.getElementById('pin-input')?.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g,'').slice(0,4);
  });
  document.getElementById('pin-input')?.addEventListener('keydown',  e => { if(e.key==='Enter') joinGame(); });
  document.getElementById('name-input')?.addEventListener('keydown', e => { if(e.key==='Enter') joinGame(); });
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
    const s = await fetchState();
    if (s && !s.error) applyState(s);
  }, ms);
}
function stopPolling() { clearInterval(pollTimer); pollTimer = null; }

/* ── Pantallas ── */
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(`screen-${name}`)?.classList.add('active');
}

/* ── Dispatcher ── */
function applyState(state) {
  const st = state.status;

  if (st === 'question' && state.has_answered) {
    if (lastStatus !== 'answered') { showScreen('answered'); lastStatus = 'answered'; }
    return;
  }
  if (st === 'waiting' && lastStatus === 'waiting') { renderLobbyCount(state); return; }

  // Durante la pregunta solo actualizar timer y contador, NO reconstruir el timeline
  if (st === 'question' && lastStatus === 'question') {
    updateQuestionTick(state);
    return;
  }

  // El estado cambió de 'question' a otra cosa (ej: 'results') → renderizar
  if (st === lastStatus) return;
  lastStatus = st;

  switch (st) {
    case 'waiting':  renderLobby(state);    break;
    case 'question': renderQuestion(state); break;
    case 'results':  renderResults(state);  break;
    case 'finished': renderFinished(state); break;
  }
}

/** Actualización ligera cada tick de polling durante la pregunta (no toca el DOM del timeline) */
function updateQuestionTick(state) {
  // Solo actualizar el contador de segundos; el countdown local ya gestiona la barra
  const pct = state.total_players > 0
    ? Math.round(((state.answer_count || 0) / state.total_players) * 100) : 0;
  // (no hay barra de respuestas en la vista player, pero dejamos el hook por si se añade)
}

/* ── Lobby ── */
function renderLobby(state) {
  showScreen('lobby');
  const p = state.player || {};
  document.getElementById('lobby-name').textContent  = p.name || '—';
  document.getElementById('lobby-count').textContent = state.total_players || 0;
  const av = document.getElementById('lobby-avatar');
  av.style.background = p.avatar_color || '#e94560';
  av.textContent = (p.name || '?')[0].toUpperCase();
  startPolling(1500);
}
function renderLobbyCount(state) {
  const el = document.getElementById('lobby-count');
  if (el) el.textContent = state.total_players || 0;
}

/* ── Question (timeline) ── */
function renderQuestion(state) {
  selectedPos  = null;
  currentSong  = state.song || {};
  lastStatus   = 'question'; // fijar aquí para que el dispatcher use la rama de tick
  questionTime = state.question_time || 30;

  showScreen('question');
  document.getElementById('q-title').textContent  = currentSong.title  || '—';
  document.getElementById('q-artist').textContent = currentSong.artist || '—';
  document.getElementById('q-round').textContent  = state.current_round;
  document.getElementById('q-total').textContent  = state.total_rounds;

  // Reset confirm
  const btn  = document.getElementById('confirm-btn');
  const hint = document.getElementById('confirm-hint');
  btn.disabled  = true;
  hint.textContent = 'Toca una posición en tu línea del tiempo';

  // Renderizar timeline con botones de posición
  buildTimeline(state.timeline || []);

  // Timer
  startTimerBar(state.time_left ?? questionTime, questionTime);
  startPolling(1000);
}

/**
 * Construye el DOM del timeline interactivo.
 * timeline: [{id, title, artist, year, genre}, ...]  — ya ordenado por año
 */
function buildTimeline(timeline) {
  const area = document.getElementById('timeline-area');
  area.innerHTML = '';

  const n = timeline.length;

  if (n === 0) {
    // No debería ocurrir (siempre tienen canción inicial)
    area.innerHTML = '<p class="text-secondary text-center p-3">Sin canciones en tu línea del tiempo</p>';
    return;
  }

  // Label
  const label = document.createElement('div');
  label.className = 'text-secondary small text-uppercase fw-semibold px-3 pt-3 pb-1';
  label.textContent = 'Tu línea del tiempo — toca dónde encaja';
  area.appendChild(label);

  // Para cada posición 0..n
  for (let i = 0; i <= n; i++) {
    // Botón de posición
    const posBtn = document.createElement('button');
    posBtn.className = 'pos-btn';
    posBtn.dataset.pos = i;
    const arrow = i === 0 ? '⬆ Antes de todo' : i === n ? '⬇ Después de todo' : '↕ Aquí';
    posBtn.innerHTML = `<span class="pos-icon">📍</span>${arrow}`;
    posBtn.addEventListener('click', () => selectPosition(i));
    area.appendChild(posBtn);

    // Canción (si existe en posición i)
    if (i < n) {
      const song = timeline[i];
      const card = document.createElement('div');
      card.className = 'timeline-song' + (i === 0 && n === 1 ? ' initial' : '');
      card.innerHTML = `
        <div class="ts-year">${song.year}</div>
        <div class="ts-info">
          <div class="ts-title">${esc(song.title)}</div>
          <div class="ts-artist">${esc(song.artist)}</div>
        </div>`;
      area.appendChild(card);
    }
  }
}

function selectPosition(pos) {
  selectedPos = pos;

  // Actualizar apariencia de todos los botones de posición
  document.querySelectorAll('.pos-btn').forEach(btn => {
    const isSelected = parseInt(btn.dataset.pos) === pos;
    btn.classList.toggle('selected', isSelected);
    if (isSelected) {
      const arrow = pos === 0 ? '⬆ Antes de todo'
                 : pos === document.querySelectorAll('.pos-btn').length - 1 ? '⬇ Después de todo'
                 : '↕ Aquí';
      btn.innerHTML = `<span class="pos-icon">✅</span>${arrow}`;
    } else {
      const i = parseInt(btn.dataset.pos);
      const total = document.querySelectorAll('.pos-btn').length;
      const txt = i === 0 ? '⬆ Antes de todo' : i === total-1 ? '⬇ Después de todo' : '↕ Aquí';
      btn.innerHTML = `<span class="pos-icon">📍</span>${txt}`;
    }
  });

  // Habilitar botón de confirmar
  const btn  = document.getElementById('confirm-btn');
  const hint = document.getElementById('confirm-hint');
  btn.disabled = false;
  hint.textContent = `Posición ${pos + 1} seleccionada — pulsa para confirmar`;

  // Scroll al botón seleccionado
  const selectedBtn = document.querySelector(`.pos-btn[data-pos="${pos}"]`);
  selectedBtn?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function confirmAnswer() {
  if (selectedPos === null) return;
  const pos = selectedPos;

  // Deshabilitar todo inmediatamente
  document.getElementById('confirm-btn').disabled = true;
  document.querySelectorAll('.pos-btn').forEach(b => b.disabled = true);
  stopCountdown();
  showScreen('answered');
  lastStatus = 'answered';

  try {
    await fetch(`${API}?action=submit_answer`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ player_id: playerId, position: pos }),
    });
  } catch { /* red inestable, ignorar */ }
}

/* ── Results ── */
function renderResults(state) {
  stopCountdown();
  showScreen('results');
  const song   = state.song     || {};
  const myRes  = state.my_result || null;

  document.getElementById('r-title').textContent  = song.title  || '—';
  document.getElementById('r-artist').textContent = song.artist || '—';
  document.getElementById('r-year').textContent   = song.year   || '—';
  document.getElementById('r-genre').textContent  = song.genre  || '';

  if (myRes) {
    const ok = !!myRes.is_correct;
    document.getElementById('res-icon').textContent = ok ? '✅' : '❌';
    document.getElementById('res-msg').textContent  = ok ? '¡Colocación correcta!' : 'Posición incorrecta';
    document.getElementById('res-msg').style.color  = ok ? 'var(--success)' : 'var(--error)';
    document.getElementById('res-pts').textContent  = ok ? `+${myRes.points_earned} pts` : '0 pts';
  } else {
    // No respondió
    document.getElementById('res-icon').textContent = '⏱️';
    document.getElementById('res-msg').textContent  = 'Tiempo agotado';
    document.getElementById('res-msg').style.color  = 'var(--muted)';
    document.getElementById('res-pts').textContent  = '0 pts';
  }

  const p = state.player || {};
  document.getElementById('res-rank').textContent =
    `Posición ${state.player_rank} / ${state.total_players}  •  Total: ${p.score} pts`;

  // Mini leaderboard
  renderMiniLB('res-leaderboard', state.round_results || [], p.name);

  // Timeline actualizado (ya incluye la canción si acertó)
  renderMiniTimeline('res-timeline', state.timeline || []);

  startPolling(2000);
}

/* ── Finished ── */
function renderFinished(state) {
  stopPolling(); stopCountdown();
  showScreen('finished');
  const p = state.player || {};
  document.getElementById('f-rank').textContent  = `Posición final: ${state.player_rank} / ${state.total_players}`;
  document.getElementById('f-score').textContent = `${p.score} pts`;

  const board = document.getElementById('f-leaderboard');
  board.innerHTML = '';
  (state.leaderboard || []).forEach((pl, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row' + (pl.name === p.name ? ' me' : '');
    const medal = ['🥇','🥈','🥉'][i] ?? `${i+1}.`;
    row.innerHTML = `
      <span class="lb-rank">${medal}</span>
      <span class="avatar-circle" style="background:${pl.avatar_color}">${pl.name[0].toUpperCase()}</span>
      <span class="lb-name">${esc(pl.name)}</span>
      <span class="lb-score">${pl.score} pts</span>`;
    board.appendChild(row);
  });
}

/* ── Helpers ── */
function renderMiniLB(id, results, myName) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = '';
  results.slice(0, 5).forEach((r, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row' + (r.name === myName ? ' me' : '');
    row.innerHTML = `
      <span class="lb-rank">${i+1}.</span>
      <span class="avatar-circle" style="background:${r.avatar_color}">${r.name[0].toUpperCase()}</span>
      <span class="lb-name">${esc(r.name)}</span>
      <span style="font-size:.85rem;color:${r.is_correct?'var(--success)':'var(--error)'}">
        ${r.is_correct?'✅':'❌'}
      </span>
      <span class="lb-score">+${r.points_earned}</span>`;
    el.appendChild(row);
  });
}

function renderMiniTimeline(id, timeline) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = '';
  if (!timeline.length) { el.innerHTML = '<p class="text-secondary small">Sin canciones aún</p>'; return; }
  timeline.forEach((s, i) => {
    const card = document.createElement('div');
    card.className = 'timeline-song mb-1' + (i === 0 ? ' initial' : '');
    card.innerHTML = `
      <div class="ts-year">${s.year}</div>
      <div class="ts-info">
        <div class="ts-title">${esc(s.title)}</div>
        <div class="ts-artist">${esc(s.artist)}</div>
      </div>`;
    el.appendChild(card);
  });
}

/* ── Timer bar ── */
function startTimerBar(seconds, total) {
  stopCountdown();
  let s = seconds;
  function tick() {
    const fill = document.getElementById('timer-fill');
    const secs = document.getElementById('q-secs');
    if (fill) fill.style.width  = Math.max(0, (s / total) * 100) + '%';
    if (secs) {
      secs.textContent   = Math.ceil(s);
      secs.style.color   = s <= 5 ? 'var(--error)' : s <= total * 0.33 ? '#d89e00' : 'var(--accent)';
    }
  }
  tick();
  countdown = setInterval(() => { s -= 1; if (s < 0) { stopCountdown(); return; } tick(); }, 1000);
}
function stopCountdown() { clearInterval(countdown); countdown = null; }

/* ── Join ── */
async function joinGame() {
  const pin  = document.getElementById('pin-input').value.trim();
  const name = document.getElementById('name-input').value.trim();
  const errEl = document.getElementById('join-error');
  errEl.classList.add('d-none');

  if (pin.length !== 4)  { showErr('El PIN debe tener 4 dígitos'); return; }
  if (!name)             { showErr('Escribe tu nombre'); return; }

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

  function showErr(msg) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
}

function goToJoin() {
  clearSession(); showScreen('join');
  document.getElementById('pin-input').value  = '';
  document.getElementById('name-input').value = '';
}
function leaveGame() {
  if (!confirm('¿Seguro que quieres salir de la partida?')) return;
  goToJoin();
}
function clearSession() {
  localStorage.removeItem(PK); localStorage.removeItem(GK);
  playerId = null; lastStatus = null; selectedPos = null;
  stopPolling(); stopCountdown();
}
function esc(s) {
  return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
