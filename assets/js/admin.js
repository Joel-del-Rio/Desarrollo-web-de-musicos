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

  // Panel de PINs individuales
  const indivPanel = document.getElementById('w-indiv-pins');
  const pinsGrid   = document.getElementById('w-pins-grid');
  const indivPins  = JSON.parse(localStorage.getItem(GK + '_indivPins') || 'null');
  const pinMode    = state.pin_mode || localStorage.getItem(GK + '_pinMode') || 'shared';
  const pinLabel   = document.getElementById('w-pin-label');

  if (indivPins && indivPins.length && pinMode === 'individual') {
    indivPanel.classList.remove('d-none');
    if (pinLabel) pinLabel.textContent = 'PIN admin (referencia)';
    if (!pinsGrid.hasChildNodes()) {
      pinsGrid.innerHTML = indivPins.map((pin, i) =>
        `<div class="pin-ticket" onclick="copyPin(this,'${pin}')" title="Clic para copiar">
           <div class="pin-ticket-num">Cartón ${i + 1}</div>
           <div class="pin-ticket-code">${pin}</div>
         </div>`
      ).join('');
    }
  } else {
    indivPanel.classList.add('d-none');
    if (pinLabel) pinLabel.textContent = 'PIN de la partida';
  }

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

function copyPin(el, pin) {
  navigator.clipboard?.writeText(pin).then(() => {
    el.classList.add('copied');
    setTimeout(() => el.classList.remove('copied'), 1200);
  });
}

function copyAllPins() {
  const pins = JSON.parse(localStorage.getItem(GK + '_indivPins') || '[]');
  const text = pins.map((p, i) => `Cartón ${i + 1}: ${p}`).join('\n');
  navigator.clipboard?.writeText(text);
  const btn = document.getElementById('btn-copy-all');
  if (btn) { btn.textContent = '✓ Copiados'; setTimeout(() => btn.textContent = '📋 Copiar todos', 1500); }
}

/* ── Question ── */
function renderQuestion(state) {
  showScreen('question');
  const song    = state.song    || {};
  const players = state.players || [];
  questionTime  = state.question_time || 30;

  // Guardar settings de la partida
  if (state.show_links !== undefined) gameSettings = { show_links: state.show_links, embed_youtube: state.embed_youtube, autoplay: state.autoplay };
  updateGridLayout();

  const qAudioCard = document.getElementById('q-audio-card');
  if (qAudioCard) qAudioCard.classList.toggle('d-none', !gameSettings.embed_youtube);

  // Si audio activo y round nuevo sin links, cargamos el audio igualmente
  if (gameSettings.embed_youtube && !gameSettings.show_links && state.current_round !== lastRenderedRound && (state.song || {}).title) {
    audioLoad('q', state.song);
  }

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

  updateGridLayout();
  renderStreamingResults(song);

  const rAudioCard = document.getElementById('r-audio-card');
  if (rAudioCard) rAudioCard.classList.toggle('d-none', !gameSettings.embed_youtube);

  // Si audio activo y no hay links, cargamos igualmente
  if (gameSettings.embed_youtube && !gameSettings.show_links && (state.song || {}).title) {
    audioLoad('r', state.song);
  }

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

/* ── Grid layout dinámico ── */
function updateGridLayout() {
  const hasMedia = gameSettings.embed_youtube || gameSettings.show_links;
  document.querySelectorAll('.admin-grid').forEach(g => g.classList.toggle('no-media', !hasMedia));
}

/* ── Setup toggles ── */
function setPinMode(btn, mode) {
  document.querySelectorAll('#pin-mode-selector .genre-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('pin-mode').value = mode;
  const isIndiv = mode === 'individual';
  document.getElementById('section-shared-email').classList.toggle('d-none', isIndiv);
  document.getElementById('section-indiv-emails').classList.toggle('d-none', !isIndiv);
  document.getElementById('pin-mode-desc').textContent = isIndiv
    ? 'Cada jugador recibirá su propio PIN único por email'
    : 'Un único PIN para toda la sala — todos lo comparten';
  if (isIndiv) updatePlayerEmailFields(parseInt(document.getElementById('indiv-count-input').value) || 2);
}

function updatePlayerEmailFields(count) {
  count = Math.max(2, Math.min(30, count));
  document.getElementById('indiv-count-input').value = count;
  const container = document.getElementById('indiv-email-fields');
  container.innerHTML = '';
  for (let i = 1; i <= count; i++) {
    const div = document.createElement('div');
    div.innerHTML = `<input type="email" class="form-control form-control-sm player-email-input"
      placeholder="Jugador ${i} — email" data-player="${i}" required>`;
    container.appendChild(div);
  }
}

function onLinksToggle() {
  // independiente — no afecta a las otras opciones
}
function onAudioToggle() {
  const on = document.getElementById('toggle-audio').checked;
  document.getElementById('row-autoplay').classList.toggle('visible', on);
  if (!on) document.getElementById('toggle-autoplay').checked = false;
}

/* ── Streaming helpers ── */
let lastRenderedRound = -1;

const SVG_SPOTIFY = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>`;
const SVG_YOUTUBE = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>`;

function streamingBtn(url, cls, svg, label) {
  return `<a href="${url}" target="_blank" rel="noopener" class="btn-stream ${cls}">${svg} ${label}</a>`;
}

function songSearchLinks(song) {
  const q = encodeURIComponent((song.title || '') + ' ' + (song.artist || ''));
  return {
    spotify: `https://open.spotify.com/search/${q}`,
    youtube: `https://www.youtube.com/results?search_query=${q}`,
  };
}

function renderStreamingQuestion(song, round) {
  const zone   = document.getElementById('q-streaming');
  const embed  = document.getElementById('q-yt-embed');
  const iframe = document.getElementById('q-yt-iframe');
  const btns   = document.getElementById('q-stream-btns');

  if (!gameSettings.show_links || !song.title) {
    zone.classList.add('d-none');
    if (iframe.src) iframe.src = '';
    return;
  }
  zone.classList.remove('d-none');

  if (round === lastRenderedRound) return;
  lastRenderedRound = round;

  // Sin URL directa de YT ya no hay embed
  embed.classList.add('d-none');
  iframe.src = '';

  const links = songSearchLinks(song);
  btns.innerHTML = '';
  btns.insertAdjacentHTML('beforeend', streamingBtn(links.spotify, 'btn-spotify', SVG_SPOTIFY, 'Spotify'));
  btns.insertAdjacentHTML('beforeend', streamingBtn(links.youtube, 'btn-youtube', SVG_YOUTUBE, 'YouTube'));

  // Cargar audio de la canción automáticamente
  if (gameSettings.embed_youtube && song.title) audioLoad('q', song);
}

function renderStreamingResults(song) {
  const zone = document.getElementById('r-streaming');
  const btns = document.getElementById('r-stream-btns');
  if (!gameSettings.show_links || !song.title) { zone.classList.add('d-none'); return; }

  const links = songSearchLinks(song);
  btns.innerHTML = '';
  btns.insertAdjacentHTML('beforeend', streamingBtn(links.spotify, 'btn-spotify', SVG_SPOTIFY, 'Spotify'));
  btns.insertAdjacentHTML('beforeend', streamingBtn(links.youtube, 'btn-youtube', SVG_YOUTUBE, 'YouTube'));
  zone.classList.remove('d-none');

  // Cargar audio de la canción automáticamente
  if (gameSettings.embed_youtube && song.title) audioLoad('r', song);
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
  const genre           = activeGenreBtn ? activeGenreBtn.dataset.genre : 'Todos';
  const showLinks       = document.getElementById('toggle-links').checked    ? '1' : '0';
  const embedYoutube    = document.getElementById('toggle-audio').checked    ? '1' : '0';
  const autoplay        = document.getElementById('toggle-autoplay').checked ? '1' : '0';
  const pinMode         = document.getElementById('pin-mode').value;
  const organizerEmail  = pinMode === 'shared' ? document.getElementById('organizer-email').value.trim() : '';
  const individualCount = Math.max(2, Math.min(30, parseInt(document.getElementById('indiv-count-input').value, 10) || 2));
  const errEl = document.getElementById('setup-error');
  errEl.classList.add('d-none');

  // Validar emails obligatorios en modo individual
  if (pinMode === 'individual') {
    const inputs = [...document.querySelectorAll('#indiv-email-fields .player-email-input')];
    const invalids = inputs.filter(inp => !inp.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inp.value.trim()));
    inputs.forEach(inp => inp.classList.remove('is-invalid'));
    if (invalids.length) {
      invalids.forEach(inp => inp.classList.add('is-invalid'));
      errEl.textContent = `Debes introducir un email válido para cada jugador (${invalids.length} pendiente${invalids.length > 1 ? 's' : ''})`;
      errEl.classList.remove('d-none');
      invalids[0].focus();
      return;
    }
  }

  try {
    const body = new URLSearchParams({
      total_rounds: rounds, question_time: time, genre,
      show_links: showLinks, embed_youtube: embedYoutube, autoplay,
      pin_mode: pinMode, organizer_email: organizerEmail, individual_count: individualCount,
    });
    if (pinMode === 'individual') {
      document.querySelectorAll('#indiv-email-fields input').forEach(inp => {
        body.append('player_emails[]', inp.value.trim());
      });
    }
    const data = await fetch(`${API}?action=create_game`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    }).then(r => r.json());

    if (data.error) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }

    gameId = String(data.id); adminToken = data.admin_token;
    localStorage.setItem(GK, gameId);
    localStorage.setItem(TK, adminToken);
    localStorage.setItem(GK + '_pin', data.pin);
    localStorage.setItem(GK + '_pinMode', data.pin_mode || 'shared');
    if (data.individual_pins?.length) {
      localStorage.setItem(GK + '_indivPins', JSON.stringify(data.individual_pins));
    } else {
      localStorage.removeItem(GK + '_indivPins');
    }

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
  [GK, TK, GK+'_pin', GK+'_pinMode', GK+'_indivPins'].forEach(k => localStorage.removeItem(k));
  gameId = adminToken = null; lastStatus = null; stopPolling(); stopTimer();
}
function esc(s) {
  return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── Reproductor de audio (iTunes Preview API) ── */
const SVG_PLAY  = `<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>`;
const SVG_PAUSE = `<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>`;

const VOL_KEY = 'hitstoric_vol';
let audioVolume = parseFloat(localStorage.getItem(VOL_KEY) ?? '0.8');

function setVolume(val) {
  audioVolume = val / 100;
  localStorage.setItem(VOL_KEY, audioVolume);
  ['q', 'r'].forEach(p => {
    const a = document.getElementById(p + '-audio');
    if (a) a.volume = audioVolume;
    const s = document.getElementById(p + '-vol');
    if (s) s.value = val;
  });
}

async function fetchItunesPreview(title, artist) {
  try {
    const q = encodeURIComponent((title || '') + ' ' + (artist || ''));
    const r = await fetch(`https://itunes.apple.com/search?term=${q}&media=music&entity=song&limit=5`);
    const data = await r.json();
    const hit = data.results?.find(t => t.previewUrl);
    return hit?.previewUrl ?? null;
  } catch {
    return null;
  }
}

async function audioLoad(p, song) {
  const a    = document.getElementById(p + '-audio');
  const time = document.getElementById(p + '-atime');

  // Detener AMBOS reproductores para evitar solapamiento
  ['q', 'r'].forEach(x => {
    const el = document.getElementById(x + '-audio');
    if (el && !el.paused) {
      el.pause();
      document.getElementById(x + '-play').innerHTML = SVG_PLAY;
    }
  });
  a.src = '';

  // Reset UI a estado de carga
  document.getElementById(p + '-play').innerHTML = SVG_PLAY;
  document.getElementById(p + '-afill').style.width = '0%';
  time.textContent = '…';

  const previewUrl = await fetchItunesPreview(song.title, song.artist);

  if (!previewUrl) {
    time.textContent = '0:00';
    return;
  }

  a.src = previewUrl;
  a.volume = audioVolume;
  a.load();
  a.onerror = () => { time.textContent = '0:00'; };

  if (gameSettings.autoplay) {
    a.oncanplay = () => {
      a.oncanplay = null;
      a.play()
        .then(() => { document.getElementById(p + '-play').innerHTML = SVG_PAUSE; })
        .catch(() => {});
    };
  }
}

function audioToggle(p) {
  const a = document.getElementById(p + '-audio');
  if (!a.src) return;
  if (a.paused) {
    a.play()
      .then(() => { document.getElementById(p + '-play').innerHTML = SVG_PAUSE; })
      .catch(() => {});
  } else {
    a.pause();
    document.getElementById(p + '-play').innerHTML = SVG_PLAY;
  }
}

function audioSeek(p, e, bar) {
  const a = document.getElementById(p + '-audio');
  if (!a.duration) return;
  const rect = bar.getBoundingClientRect();
  a.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * a.duration;
}

function fmtTime(s) {
  const m  = Math.floor(s / 60);
  const ss = Math.floor(s % 60).toString().padStart(2, '0');
  return `${m}:${ss}`;
}

function initAudio(p) {
  const a = document.getElementById(p + '-audio');
  if (!a) return;
  // Restaurar volumen guardado y sincronizar slider
  a.volume = audioVolume;
  const slider = document.getElementById(p + '-vol');
  if (slider) slider.value = Math.round(audioVolume * 100);
  a.addEventListener('timeupdate', () => {
    if (!a.duration) return;
    document.getElementById(p + '-afill').style.width = (a.currentTime / a.duration * 100) + '%';
    document.getElementById(p + '-atime').textContent = fmtTime(a.currentTime) + ' / ' + fmtTime(a.duration);
  });
  a.addEventListener('ended', () => { document.getElementById(p + '-play').innerHTML = SVG_PLAY; });
  a.addEventListener('loadedmetadata', () => {
    document.getElementById(p + '-atime').textContent = '0:00 / ' + fmtTime(a.duration);
  });
}

initAudio('q');
initAudio('r');
