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

/* ── Estado del reproductor de audio ─────────────────────────── */
const P_SVG_PLAY  = `<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>`;
const P_SVG_PAUSE = `<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>`;
const P_VOL_KEY   = 'hitstoric_p_vol';
let playerAudioVolume = parseFloat(localStorage.getItem(P_VOL_KEY) ?? '0.8');
let pCurrentSongKey   = '';   // "title|artist" de la canción actual
let pPreviewUrl       = null; // URL cargada para la canción actual
let pPreviewLoading   = false;

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
  initPlayerAudio();
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
    if (lastStatus !== 'answered') {
      showScreen('answered');
      lastStatus = 'answered';
    }
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
  // El countdown local ya gestiona la barra visual; actualizar racha si cambia
  updateStreakDisplay(state.player);
}

/** Muestra u oculta el indicador de racha en la cabecera de la pregunta */
function updateStreakDisplay(player) {
  const box = document.getElementById('q-streak-box');
  if (!box) return;
  const streak = (player && player.streak) ? parseInt(player.streak) : 0;
  if (streak >= 3) {
    const mult = Math.min(2.0, 1.0 + streak * 0.1).toFixed(1);
    document.getElementById('q-streak-count').textContent = streak;
    document.getElementById('q-multiplier').textContent   = mult;
    box.classList.remove('d-none');
  } else {
    box.classList.add('d-none');
  }
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
  const artistEl = document.getElementById('q-artist');
  if (state.hard_mode) {
    artistEl.textContent = '???';
    artistEl.style.opacity = '.35';
  } else {
    artistEl.textContent = currentSong.artist || '—';
    artistEl.style.opacity = '';
  }
  document.getElementById('q-round').textContent  = state.current_round;
  document.getElementById('q-total').textContent  = state.total_rounds;
  document.getElementById('hard-mode-badge')?.classList.toggle('d-none', !state.hard_mode);

  // Reset del botón de confirmación
  const btn  = document.getElementById('confirm-btn');
  const hint = document.getElementById('confirm-hint');
  btn.disabled  = true;
  btn.classList.remove('btn-pulse');
  hint.textContent = 'Toca una posición en tu línea del tiempo';

  buildTimeline(state.timeline || []);
  updateStreakDisplay(state.player);
  // Timer y polling arrancan inmediatamente; el audio carga en segundo plano
  startTimerBar(state.time_left ?? questionTime, questionTime);
  startPolling(1000);
  renderAudio(state).catch(e => console.error('[renderAudio EXCEPCION]', e)); // fire-and-forget: no bloquea el timer ni el polling
}

/* ── Reproductor de audio del jugador (iTunes Preview via proxy PHP) ── */

function initPlayerAudio() {
  const a = document.getElementById('p-audio');
  if (!a) return;
  a.volume = playerAudioVolume;
  const slider = document.getElementById('p-vol');
  if (slider) slider.value = Math.round(playerAudioVolume * 100);
  a.addEventListener('timeupdate', () => {
    if (!a.duration) return;
    document.getElementById('p-afill').style.width = (a.currentTime / a.duration * 100) + '%';
    document.getElementById('p-atime').textContent = pFmtTime(a.currentTime) + ' / ' + pFmtTime(a.duration);
  });
  a.addEventListener('ended',          () => { document.getElementById('p-play').innerHTML = P_SVG_PLAY; });
  a.addEventListener('loadedmetadata', () => { document.getElementById('p-atime').textContent = '0:00 / ' + pFmtTime(a.duration); });
}

function pFmtTime(s) {
  const m = Math.floor(s / 60);
  return `${m}:${Math.floor(s % 60).toString().padStart(2, '0')}`;
}

function playerSetVolume(val) {
  playerAudioVolume = val / 100;
  localStorage.setItem(P_VOL_KEY, playerAudioVolume);
  const a = document.getElementById('p-audio');
  if (a) a.volume = playerAudioVolume;
}

/** Llamado cuando el usuario pulsa el botón Play/Pausa */
async function playerAudioToggle() {
  const a       = document.getElementById('p-audio');
  const playBtn = document.getElementById('p-play');
  const timeEl  = document.getElementById('p-atime');
  if (!a) return;

  // Si ya tiene src y está cargado → toggle normal
  if (a.getAttribute('src') && a.readyState >= 1) {
    if (a.paused) {
      a.play()
        .then(() => { if (playBtn) playBtn.innerHTML = P_SVG_PAUSE; })
        .catch(() => {});
    } else {
      a.pause();
      if (playBtn) playBtn.innerHTML = P_SVG_PLAY;
    }
    return;
  }

  // Si tenemos la URL pre-cargada → asignar y reproducir
  if (pPreviewUrl) {
    if (timeEl) timeEl.textContent = '…';
    a.src    = pPreviewUrl;
    a.volume = playerAudioVolume;
    a.load();
    a.oncanplay = () => {
      a.oncanplay = null;
      a.play().then(() => { if (playBtn) playBtn.innerHTML = P_SVG_PAUSE; }).catch(() => {});
    };
    return;
  }

  // Si todavía está cargando → esperar
  if (pPreviewLoading) {
    if (timeEl) timeEl.textContent = '…';
    return;
  }

  // Sin URL disponible → mostrar indicador
  if (timeEl) timeEl.textContent = '—';
}

function playerAudioSeek(e, bar) {
  const a = document.getElementById('p-audio');
  if (!a || !a.duration) return;
  const rect = bar.getBoundingClientRect();
  a.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * a.duration;
}

/**
 * Prepara el reproductor para una nueva canción.
 * Busca la URL de preview vía proxy PHP y, si autoplay está activo, reproduce automáticamente.
 * Se llama fire-and-forget desde renderQuestion.
 */
async function renderAudio(state) {
  const section   = document.getElementById('audio-section');
  const linksEl   = document.getElementById('audio-links');
  const song      = state.song || {};
  const embedYT   = !!state.embed_youtube;
  const autoplay  = !!state.autoplay;
  const showLinks = !!state.show_links;

  linksEl.innerHTML = '';
  section.classList.toggle('d-none', !embedYT);

  const log = () => {};

  if (embedYT && song.title) {
    const songKey = `${song.title}|${song.artist}`;
    log(`2 key=${songKey} prev=${pCurrentSongKey}`);
    const a       = document.getElementById('p-audio');
    const playBtn = document.getElementById('p-play');
    const fillEl  = document.getElementById('p-afill');
    const timeEl  = document.getElementById('p-atime');
    log(`3 a=${!!a} timeEl=${!!timeEl}`);

    // Resetear estado si es una canción distinta
    if (songKey !== pCurrentSongKey) {
      log('4 nueva cancion');
      pCurrentSongKey = songKey;
      pPreviewUrl     = null;
      pPreviewLoading = true;
      if (a) { a.pause(); a.removeAttribute('src'); a.load(); }
      if (playBtn) playBtn.innerHTML = P_SVG_PLAY;
      if (fillEl)  fillEl.style.width = '0%';
      if (timeEl)  timeEl.textContent = '…';
      log('Buscando preview…');

      // Buscar preview vía proxy PHP
      try {
        const q = encodeURIComponent(song.title + ' ' + (song.artist || ''));
        const url = `${API}?action=itunes_preview&term=${q}`;
        log('Fetch: ' + url);
        const r = await fetch(url, { cache: 'no-store' });
        const d = await r.json();
        pPreviewUrl = d.previewUrl ?? null;
        log(pPreviewUrl ? 'URL: ' + pPreviewUrl.slice(0, 60) + '…' : 'Sin preview');
      } catch(e) {
        pPreviewUrl = null;
        log('Error fetch: ' + e.message);
      }
      pPreviewLoading = false;

      if (!pPreviewUrl) {
        if (timeEl) timeEl.textContent = '—';
      } else if (a) {
        a.src    = pPreviewUrl;
        a.volume = playerAudioVolume;
        a.onerror = (e) => {
          log('Error audio: ' + (a.error?.message || 'desconocido'));
          if (timeEl) timeEl.textContent = '—';
        };
        a.load();
        log('Audio cargando…');

        // Siempre autoplay al detectar canción nueva (igual que el dinamizador)
        a.oncanplay = () => {
          a.oncanplay = null;
          log('Reproduciendo…');
          a.play()
            .then(() => { if (playBtn) playBtn.innerHTML = P_SVG_PAUSE; })
            .catch(err => { log('Autoplay bloqueado: ' + err.message); });
        };
      }
    } else {
      log(pPreviewUrl ? 'Misma canción, URL lista' : 'Misma canción, sin preview');
    }
  }

  // Botones de streaming (si el admin también activó show_links)
  if (showLinks) {
    if (song.spotify_url) {
      linksEl.insertAdjacentHTML('beforeend',
        `<a href="${song.spotify_url}" target="_blank" rel="noopener" class="btn btn-stream btn-spotify">
           <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
           Spotify
         </a>`
      );
    }
    if (song.youtube_url) {
      linksEl.insertAdjacentHTML('beforeend',
        `<a href="${song.youtube_url}" target="_blank" rel="noopener" class="btn btn-stream btn-youtube">
           <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>
           YouTube
         </a>`
      );
    }
  }
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
  btn.classList.add('btn-pulse');
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

  const confirmBtn = document.getElementById('confirm-btn');
  confirmBtn.disabled = true;
  confirmBtn.classList.remove('btn-pulse');
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

  // Mostrar si acertó o no, puntos y racha
  const streak    = parseInt((state.player || {}).streak || 0);
  const streakEl  = document.getElementById('res-streak');
  streakEl.classList.add('d-none');
  streakEl.innerHTML = '';

  if (myRes) {
    const ok = !!myRes.is_correct;
    document.getElementById('res-icon').textContent = ok ? '✅' : '❌';
    document.getElementById('res-msg').textContent  = ok ? '¡Colocación correcta!' : 'Posición incorrecta';
    document.getElementById('res-msg').style.color  = ok ? 'var(--success)' : 'var(--error)';
    document.getElementById('res-pts').textContent  = ok ? `+${myRes.points_earned} pts` : '0 pts';

    if (ok && streak >= 3) {
      // Desglosar: puntos base × multiplicador
      const mult = Math.min(2.0, 1.0 + streak * 0.1);
      const base = Math.round(myRes.points_earned / mult);
      streakEl.innerHTML =
        `${base} pts base &times; <span style="font-size:1.1em">${mult.toFixed(1)}</span>`
        + ` &nbsp;·&nbsp; 🔥 Racha ${streak}`;
      streakEl.classList.remove('d-none');
    } else if (ok && streak > 0) {
      // Racha en curso pero sin multiplicador activo todavía — mostrar puntos + racha
      streakEl.innerHTML = `+${myRes.points_earned} pts &nbsp;·&nbsp; 🔥 Racha ${streak}`;
      streakEl.classList.remove('d-none');
    }
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
