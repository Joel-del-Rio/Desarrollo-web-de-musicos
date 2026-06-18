/* ═══════════════════════════════════════════════════════════════
 * player.js — Vista del jugador
 *
 * Gestiona toda la interacción del jugador: unirse con PIN,
 * ver el lobby, colocar canciones en su línea del tiempo,
 * enviar la respuesta y ver los resultados de cada ronda.
 * Usa polling cada 1-2s para sincronizar el estado con el servidor.
 * ═══════════════════════════════════════════════════════════════ */

/* ── Estado global ────────────────────────────────────────────── */
let playerId     = parseInt(localStorage.getItem(PK), 10) || null;
let pollTimer    = null;
let countdown    = null;        // Intervalo de la barra de cuenta atrás
let lastStatus   = null;        // Último estado procesado (evita re-renders duplicados)
let selectedPos  = null;        // Posición seleccionada en el timeline (índice)
let currentSong  = null;        // Canción de la ronda actual
let questionTime = 30;          // Duración de la pregunta en segundos

/* ── Arranque ── */
(function init() {
  // Pre-rellenar el input del PIN si viene en la URL (?pin=XXXX)
  const urlPin = new URLSearchParams(window.location.search).get('pin');
  if (urlPin) {
    const pinInput = document.getElementById('pin-input');
    if (pinInput) pinInput.value = urlPin.replace(/\D/g, '').slice(0, 4);
  }

  if (playerId) {
    // Sesión guardada — intentar recuperar el estado
    fetchState().then(state => {
      if (state && !state.error) applyState(state);
      else { clearSession(); showScreen('join'); }
    });
  } else {
    showScreen('join');
  }

  // Forzar que el PIN solo acepte dígitos (máx 4)
  document.getElementById('pin-input')?.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g,'').slice(0,4);
  });
  // Enviar con Enter en el formulario de join
  document.getElementById('pin-input')?.addEventListener('keydown',  e => { if(e.key==='Enter') joinGame(); });
  document.getElementById('name-input')?.addEventListener('keydown', e => { if(e.key==='Enter') joinGame(); });
})();

/* ── Llamada API ──────────────────────────────────────────────── */
async function fetchState() {
  try {
    const r = await fetch(`${API}?action=player_state&player_id=${playerId}&_t=${Date.now()}`, { cache: 'no-store' });
    return r.json();
  } catch { return null; }
}

/* ── Polling periódico ────────────────────────────────────────── */
function startPolling(ms = 1500) {
  stopPolling();
  pollTimer = setInterval(async () => {
    const s = await fetchState();
    if (s && !s.error) applyState(s);
  }, ms);
}
function stopPolling() { clearInterval(pollTimer); pollTimer = null; }

/* ── Gestión de pantallas ─────────────────────────────────────── */
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(`screen-${name}`)?.classList.add('active');
}

/* ── Dispatcher de estado ─────────────────────────────────────── */
function applyState(state) {
  const st = state.status;

  // Si el jugador ya respondió, ir a pantalla de espera
  if (st === 'question' && state.has_answered) {
    if (lastStatus !== 'answered') { showScreen('answered'); lastStatus = 'answered'; }
    return;
  }
  // Transición local 'answered' → evitar que reaparezca el timeline antes de que el
  // servidor confirme el cambio (el siguiente poll mostrará 'results')
  if (lastStatus === 'answered' && st === 'question') return;

  // Actualización ligera del contador del lobby
  if (st === 'waiting' && lastStatus === 'waiting') { renderLobbyCount(state); return; }

  // Durante la pregunta solo actualizar el timer local, no reconstruir el timeline
  if (st === 'question' && lastStatus === 'question') {
    updateQuestionTick(state);
    return;
  }

  if (st === lastStatus) return;
  lastStatus = st;

  switch (st) {
    case 'waiting':  renderLobby(state);    break;
    case 'question': renderQuestion(state); break;
    case 'results':  renderResults(state);  break;
    case 'finished': renderFinished(state); break;
  }
}

/** Actualización ligera cada poll durante la pregunta (no toca el DOM del timeline) */
function updateQuestionTick(state) {
  // El countdown local ya gestiona la barra visual; aquí no hay más widgets que actualizar
  const pct = state.total_players > 0
    ? Math.round(((state.answer_count || 0) / state.total_players) * 100) : 0;
}

/* ── Lobby ────────────────────────────────────────────────────── */
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

/** Actualiza solo el contador de jugadores en el lobby sin re-renderizar toda la pantalla */
function renderLobbyCount(state) {
  const el = document.getElementById('lobby-count');
  if (el) el.textContent = state.total_players || 0;
}

/* ── Pantalla de pregunta (timeline interactivo) ──────────────── */
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

  // Reset del botón de confirmación
  const btn  = document.getElementById('confirm-btn');
  const hint = document.getElementById('confirm-hint');
  btn.disabled  = true;
  hint.textContent = 'Toca una posición en tu línea del tiempo';

  buildTimeline(state.timeline || []);

  startTimerBar(state.time_left ?? questionTime, questionTime);
  startPolling(1000);
}

/**
 * Construye el DOM del timeline interactivo.
 * Alterna entre botones de posición y tarjetas de canción.
 * El jugador pulsa un botón de posición para indicar dónde encaja la nueva canción.
 */
function buildTimeline(timeline) {
  const area = document.getElementById('timeline-area');
  area.innerHTML = '';

  const n = timeline.length;

  if (n === 0) {
    area.innerHTML = '<p class="text-secondary text-center p-3">Sin canciones en tu línea del tiempo</p>';
    return;
  }

  const label = document.createElement('div');
  label.className = 'text-secondary small text-uppercase fw-semibold px-3 pt-3 pb-1';
  label.textContent = 'Tu línea del tiempo — toca dónde encaja';
  area.appendChild(label);

  // Generar N+1 botones de posición intercalados con N tarjetas de canción
  for (let i = 0; i <= n; i++) {
    const posBtn = document.createElement('button');
    posBtn.className = 'pos-btn';
    posBtn.dataset.pos = i;
    const arrow = i === 0 ? '⬆ Antes de todo' : i === n ? '⬇ Después de todo' : '↕ Aquí';
    posBtn.innerHTML = `<span class="pos-icon">📍</span>${arrow}`;
    posBtn.addEventListener('click', () => selectPosition(i));
    area.appendChild(posBtn);

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

/** Marca visualmente la posición seleccionada y habilita el botón de confirmar */
function selectPosition(pos) {
  selectedPos = pos;

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

  const btn  = document.getElementById('confirm-btn');
  const hint = document.getElementById('confirm-hint');
  btn.disabled = false;
  hint.textContent = `Posición ${pos + 1} seleccionada — pulsa para confirmar`;

  document.querySelector(`.pos-btn[data-pos="${pos}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Envía la respuesta al servidor.
 * Si el servidor la rechaza (ej: ya respondió), reactiva la UI para reintentar.
 * Si hay error de red, igualmente muestra la pantalla de espera (el servidor
 * puede haberla recibido aunque la respuesta no llegara al cliente).
 */
async function confirmAnswer() {
  if (selectedPos === null) return;
  const pos = selectedPos;

  document.getElementById('confirm-btn').disabled = true;
  document.querySelectorAll('.pos-btn').forEach(b => b.disabled = true);
  stopCountdown();

  try {
    const r = await fetch(`${API}?action=submit_answer`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ player_id: playerId, position: pos }),
    });
    const d = await r.json();
    if (d.error) {
      // El servidor rechazó la respuesta — reactivar la UI para reintentar
      console.error('[Player] submit_answer rechazado:', d.error);
      document.getElementById('confirm-btn').disabled = false;
      document.querySelectorAll('.pos-btn').forEach(b => b.disabled = false);
      const hint = document.getElementById('confirm-hint');
      if (hint) hint.textContent = 'Error: ' + d.error;
      return;
    }
    showScreen('answered');
    lastStatus = 'answered';
  } catch {
    // Red inestable: ir a espera igualmente (el polling detectará el estado real)
    showScreen('answered');
    lastStatus = 'answered';
  }
}

/* ── Pantalla de resultados ───────────────────────────────────── */
function renderResults(state) {
  stopCountdown();
  showScreen('results');
  const song   = state.song     || {};
  const myRes  = state.my_result || null;

  document.getElementById('r-title').textContent  = song.title  || '—';
  document.getElementById('r-artist').textContent = song.artist || '—';
  document.getElementById('r-year').textContent   = song.year   || '—';
  document.getElementById('r-genre').textContent  = song.genre  || '';

  // Mostrar si acertó o no, y los puntos ganados
  if (myRes) {
    const ok = !!myRes.is_correct;
    document.getElementById('res-icon').textContent = ok ? '✅' : '❌';
    document.getElementById('res-msg').textContent  = ok ? '¡Colocación correcta!' : 'Posición incorrecta';
    document.getElementById('res-msg').style.color  = ok ? 'var(--success)' : 'var(--error)';
    document.getElementById('res-pts').textContent  = ok ? `+${myRes.points_earned} pts` : '0 pts';
  } else {
    // No respondió a tiempo
    document.getElementById('res-icon').textContent = '⏱️';
    document.getElementById('res-msg').textContent  = 'Tiempo agotado';
    document.getElementById('res-msg').style.color  = 'var(--muted)';
    document.getElementById('res-pts').textContent  = '0 pts';
  }

  const p = state.player || {};
  document.getElementById('res-rank').textContent =
    `Posición ${state.player_rank} / ${state.total_players}  •  Total: ${p.score} pts`;

  renderMiniLB('res-leaderboard', state.round_results || [], p.name);
  renderMiniTimeline('res-timeline', state.timeline || []);

  startPolling(2000);
}

/* ── Pantalla final ───────────────────────────────────────────── */
function renderFinished(state) {
  stopPolling(); stopCountdown();
  showScreen('finished');
  const p = state.player || {};
  document.getElementById('f-rank').textContent  = `Posición final: ${state.player_rank} / ${state.total_players}`;
  document.getElementById('f-score').textContent = `${p.score} pts`;

  // En modo PIN individual, informar sobre el ranking global de premios
  const prizeEl = document.getElementById('f-prize');
  if (prizeEl) {
    if (state.pin_mode === 'individual') {
      prizeEl.innerHTML = `🏆 ¡Has acumulado <strong>${p.score} pts</strong> en el ranking global!<br><span style="font-size:.85rem;font-weight:400">Visita <a href="premios" style="color:var(--accent)">Premios</a> para ver cuántos tienes y canjearlos.</span>`;
      prizeEl.classList.remove('d-none');
    } else {
      prizeEl.classList.add('d-none');
    }
  }

  // Tabla de clasificación final completa
  const board = document.getElementById('f-leaderboard');
  board.innerHTML = '';
  (state.leaderboard || []).forEach((pl, i) => {
    const row = document.createElement('div');
    // Resaltar la fila del jugador actual
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

/* ── Helpers de renderizado ───────────────────────────────────── */

/** Mini-leaderboard de la ronda (top 5), resaltando la fila del jugador actual */
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

/** Renderiza el timeline actualizado del jugador en la pantalla de resultados */
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

/* ── Barra de cuenta atrás ────────────────────────────────────── */
function startTimerBar(seconds, total) {
  stopCountdown();
  let s = seconds;
  function tick() {
    const fill = document.getElementById('timer-fill');
    const secs = document.getElementById('q-secs');
    if (fill) fill.style.width  = Math.max(0, (s / total) * 100) + '%';
    if (secs) {
      secs.textContent   = Math.ceil(s);
      // Verde → naranja → rojo según tiempo restante
      secs.style.color   = s <= 5 ? 'var(--error)' : s <= total * 0.33 ? '#d89e00' : 'var(--accent)';
    }
  }
  tick();
  countdown = setInterval(() => { s -= 1; if (s < 0) { stopCountdown(); return; } tick(); }, 1000);
}
function stopCountdown() { clearInterval(countdown); countdown = null; }

/* ── Formulario de join ───────────────────────────────────────── */
async function joinGame() {
  const pin   = document.getElementById('pin-input').value.trim();
  const name  = document.getElementById('name-input').value.trim();
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

/** Borra la sesión del jugador del localStorage */
function clearSession() {
  localStorage.removeItem(PK); localStorage.removeItem(GK);
  playerId = null; lastStatus = null; selectedPos = null;
  stopPolling(); stopCountdown();
}

/** Escapa caracteres HTML para evitar XSS al insertar texto de usuario en el DOM */
function esc(s) {
  return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
