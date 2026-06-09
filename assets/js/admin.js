/* ── Estado ── */
let gameId     = localStorage.getItem(GK);
let adminToken = localStorage.getItem(TK);
let pollTimer  = null;
let timerInterval = null;
let lastStatus = null;
let questionTime = 30;
let gameSettings = { show_links: 0, embed_youtube: 0, autoplay: 0 };

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

  // Guardar settings de la partida
  if (state.show_links !== undefined) gameSettings = { show_links: state.show_links, embed_youtube: state.embed_youtube, autoplay: state.autoplay };

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

  // Streaming
  renderStreamingQuestion(song, state.current_round);

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

  renderStreamingResults(song);

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
  const top3 = players.slice(0, 3);
  // Orden visual: 2º izq, 1º centro, 3º dcha — con sus índices originales
  const slots = [
    { p: top3[1], cls: 'podium-2nd', medal: '🥈' },
    { p: top3[0], cls: 'podium-1st', medal: '🥇' },
    { p: top3[2], cls: 'podium-3rd', medal: '🥉' },
  ].filter(s => s.p);
  el.innerHTML = '';
  slots.forEach(({ p, cls, medal }) => {
    const step = document.createElement('div');
    step.className = `podium-step ${cls}`;
    step.innerHTML = `
      <div class="podium-medal" style="font-size:1.75rem">${medal}</div>
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

/* ── Setup toggles ── */
function onLinksToggle() {
  const on = document.getElementById('toggle-links').checked;
  document.getElementById('row-embed').style.opacity    = on ? '1' : '.4';
  document.getElementById('row-embed').style.pointerEvents = on ? '' : 'none';
  if (!on) {
    document.getElementById('toggle-embed').checked = false;
    onEmbedToggle();
  }
}
function onEmbedToggle() {
  const on = document.getElementById('toggle-embed').checked;
  const row = document.getElementById('row-autoplay');
  row.style.display = on ? 'flex' : 'none';
}

/* ── Streaming helpers ── */
function getYouTubeId(url) {
  if (!url) return null;
  const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/|v\/))([A-Za-z0-9_-]{11})/);
  return m ? m[1] : null;
}

let lastRenderedRound = -1;

const SVG_SPOTIFY = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>`;
const SVG_YOUTUBE = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>`;

function streamingBtn(url, cls, svg, label) {
  return `<a href="${url}" target="_blank" rel="noopener" class="btn-stream ${cls}">${svg} ${label}</a>`;
}

function renderStreamingQuestion(song, round) {
  const zone   = document.getElementById('q-streaming');
  const embed  = document.getElementById('q-yt-embed');
  const iframe = document.getElementById('q-yt-iframe');
  const btns   = document.getElementById('q-stream-btns');

  // Ocultar toda la zona si los links están desactivados
  if (!gameSettings.show_links) {
    zone.classList.add('d-none');
    if (iframe.src) iframe.src = ''; // detener reproducción
    return;
  }
  zone.classList.remove('d-none');

  // Solo re-renderizar cuando cambia de ronda (evita parpadeo en cada poll)
  if (round === lastRenderedRound) return;
  lastRenderedRound = round;

  const ytId = getYouTubeId(song.youtube_url);

  // ── Embed YouTube ──
  if (gameSettings.embed_youtube && ytId) {
    const ap = gameSettings.autoplay ? 1 : 0;
    // Pequeño delay para que el iframe esté visible antes de asignar src (mejora autoplay)
    embed.classList.remove('d-none');
    setTimeout(() => {
      iframe.src = `https://www.youtube.com/embed/${ytId}?autoplay=${ap}&rel=0&modestbranding=1&enablejsapi=1`;
    }, 80);
  } else {
    embed.classList.add('d-none');
    iframe.src = '';
  }

  // ── Botones de plataforma ──
  btns.innerHTML = '';
  if (song.spotify_url) btns.insertAdjacentHTML('beforeend', streamingBtn(song.spotify_url, 'btn-spotify', SVG_SPOTIFY, 'Spotify'));
  // Mostrar botón YouTube solo si NO hay embed activo
  if (song.youtube_url && !gameSettings.embed_youtube) btns.insertAdjacentHTML('beforeend', streamingBtn(song.youtube_url, 'btn-youtube', SVG_YOUTUBE, 'YouTube'));
  // Si hay embed activo, ofrecer también el enlace directo por si el autoplay falla
  if (song.youtube_url && gameSettings.embed_youtube)  btns.insertAdjacentHTML('beforeend', streamingBtn(song.youtube_url, 'btn-youtube', SVG_YOUTUBE, 'Abrir en YouTube'));
}

function renderStreamingResults(song) {
  const zone = document.getElementById('r-streaming');
  const btns = document.getElementById('r-stream-btns');
  if (!gameSettings.show_links) { zone.classList.add('d-none'); return; }

  btns.innerHTML = '';
  if (song.spotify_url) btns.insertAdjacentHTML('beforeend', streamingBtn(song.spotify_url, 'btn-spotify', SVG_SPOTIFY, 'Spotify'));
  if (song.youtube_url) btns.insertAdjacentHTML('beforeend', streamingBtn(song.youtube_url, 'btn-youtube', SVG_YOUTUBE, 'YouTube'));
  zone.classList.toggle('d-none', btns.innerHTML === '');
}

/* ── Acciones admin ── */
function selectGenre(btn) {
  document.querySelectorAll('#genre-selector .genre-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function createGame() {
  const rounds = parseInt(document.getElementById('rounds-input').value, 10);
  const time   = parseInt(document.getElementById('time-input').value,   10);
  const activeGenreBtn = document.querySelector('#genre-selector .genre-btn.active');
  const genre        = activeGenreBtn ? activeGenreBtn.dataset.genre : 'Todos';
  const showLinks    = document.getElementById('toggle-links').checked    ? '1' : '0';
  const embedYoutube = document.getElementById('toggle-embed').checked    ? '1' : '0';
  const autoplay     = document.getElementById('toggle-autoplay').checked ? '1' : '0';
  const errEl  = document.getElementById('setup-error');
  errEl.classList.add('d-none');
  try {
    const data = await fetch(`${API}?action=create_game`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ total_rounds: rounds, question_time: time, genre, show_links: showLinks, embed_youtube: embedYoutube, autoplay }),
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
