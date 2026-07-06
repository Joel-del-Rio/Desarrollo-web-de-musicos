<?php
/**
 * admin.php — Panel del dinamizador
 *
 * SPA de una sola página que muestra las pantallas de la partida en orden:
 * setup → waiting → question → results → finished.
 * El JS (admin.js) controla qué pantalla está visible en cada momento
 * mediante polling al servidor y la clase CSS 'active' en .screen.
 *
 * Constantes JS exportadas al script:
 *   API = URL de la API (Controlador/api.php)
 *   GK  = clave localStorage para el ID de la partida
 *   TK  = clave localStorage para el token de admin
 */
require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Dinamizador</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=7">
</head>
<body>

<!-- ══ SETUP ══ -->
<div id="screen-setup" class="screen active align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column" style="max-width:500px">

    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
    </div>
    <p class="text-secondary text-center mb-2">Panel del Dinamizador</p>

    <div class="card p-4">
      <h5 class="fw-bold mb-4">Configurar partida</h5>

      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Número de rondas</label>
        <div class="text-center fw-black mb-1" style="font-size:3rem;color:var(--accent)" id="rounds-display">10</div>
        <input type="range" class="form-range" id="rounds-input" min="5" max="20" value="10"
               oninput="document.getElementById('rounds-display').textContent=this.value">
        <div class="d-flex justify-content-between text-secondary small"><span>5</span><span>20</span></div>
      </div>

      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Tiempo por ronda (segundos)</label>
        <div class="text-center fw-black mb-1" style="font-size:3rem;color:var(--accent)" id="time-display">30</div>
        <input type="range" class="form-range" id="time-input" min="20" max="60" value="30" step="5"
               oninput="document.getElementById('time-display').textContent=this.value">
        <div class="d-flex justify-content-between text-secondary small"><span>20s</span><span>60s</span></div>
      </div>

      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Género musical</label>
        <div class="d-flex flex-wrap gap-2 mt-2" id="genre-selector">
          <?php foreach (GENRES as $g):
            $active = $g === 'Todos' ? ' active' : '';
          ?>
          <button type="button"
                  class="btn btn-sm rounded-pill genre-btn<?= $active ?>"
                  onclick="selectGenre(this)"
                  data-genre="<?= htmlspecialchars($g) ?>">
            <?= htmlspecialchars($g) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Modo difícil -->
      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Dificultad</label>
        <div class="d-flex align-items-center justify-content-between py-2 rounded-3 px-3"
             style="background:rgba(233,69,96,.08);border:1px solid rgba(233,69,96,.25)">
          <div>
            <div class="fw-semibold small">🔥 Modo difícil</div>
            <div class="small" style="color:var(--muted);opacity:.75">Oculta el artista y el año · desactiva streaming</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-hard-mode" role="switch"
                   onchange="onHardModeToggle()">
          </div>
        </div>
      </div>

      <!-- Opciones de streaming -->
      <div class="mb-4" id="section-streaming">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Streaming</label>

        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary border-opacity-25">
          <div>
            <div class="fw-semibold small">Mostrar enlaces a plataformas</div>
            <div class="small" style="color:var(--muted);opacity:.75">Spotify y YouTube junto a cada canción</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-links" role="switch"
                   onchange="onLinksToggle()">
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary border-opacity-25" id="row-audio">
          <div>
            <div class="fw-semibold small">Audio de la canción</div>
            <div class="small" style="color:var(--muted);opacity:.75">Muestra el reproductor de audio en partida</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-audio" role="switch"
                   onchange="onAudioToggle()">
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between py-2" id="row-autoplay">
          <div>
            <div class="fw-semibold small">Autoplay al cargar audio</div>
            <div class="small" style="color:var(--muted);opacity:.75">El audio empieza al cargar el archivo</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-autoplay" role="switch">
          </div>
        </div>
      </div>

      <!-- Modo PIN -->
      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Modo de acceso</label>
        <div class="d-flex gap-2 mt-2" id="pin-mode-selector">
          <button type="button" class="btn btn-sm rounded-pill genre-btn active"
                  onclick="setPinMode(this,'shared')" data-mode="shared">🔑 PIN compartido</button>
          <button type="button" class="btn btn-sm rounded-pill genre-btn"
                  onclick="setPinMode(this,'individual')" data-mode="individual">🎟️ PINs individuales</button>
        </div>

        <!-- Descripción detallada del modo seleccionado -->
        <div id="pin-mode-desc-shared" class="mt-3 p-3 rounded-3" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)">
          <div class="fw-semibold small mb-2">🔑 PIN compartido</div>
          <ul class="small mb-0 ps-3" style="color:var(--muted);line-height:1.9">
            <li>Se genera un único PIN de 4 dígitos para toda la sala.</li>
            <li>Los jugadores lo introducen ellos mismos en su móvil para unirse.</li>
            <li>Ideal para partidas en grupo presencial (proyectas el PIN en pantalla).</li>
            <li>Los puntos <strong style="color:#fff">no</strong> se acumulan en el ranking global de premios.</li>
          </ul>
        </div>
        <div id="pin-mode-desc-individual" class="mt-3 p-3 rounded-3 d-none" style="background:rgba(233,69,96,.06);border:1px solid rgba(233,69,96,.2)">
          <div class="fw-semibold small mb-2" style="color:var(--accent)">🎟️ PINs individuales</div>
          <ul class="small mb-0 ps-3" style="color:var(--muted);line-height:1.9">
            <li>Se genera un PIN único y personal para cada jugador.</li>
            <li>Cada jugador recibe su PIN por email — no hace falta que estén presentes para apuntarse.</li>
            <li>Los puntos <strong style="color:#fff">sí</strong> se acumulan en el ranking global y pueden canjearse por premios.</li>
            <li>Requiere introducir el email de cada jugador antes de crear la partida.</li>
          </ul>
        </div>

        <input type="hidden" id="pin-mode" value="shared">
      </div>

      <!-- Email organizador (modo compartido) -->
      <div class="mb-4" id="section-shared-email">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Tu email <span class="text-danger">*</span></label>
        <input type="email" id="organizer-email" class="form-control" placeholder="tucorreo@ejemplo.com" required>
        <div class="form-text small" style="color:var(--muted)">Recibirás el PIN al crear la partida</div>
      </div>

      <!-- Emails individuales (modo individual, oculto por defecto) -->
      <div class="d-none" id="section-indiv-emails">
        <div class="mb-3">
          <label class="form-label text-secondary small fw-semibold text-uppercase">Número de jugadores</label>
          <input type="number" id="indiv-count-input" class="form-control" min="2" max="30" value="2"
                 oninput="updatePlayerEmailFields(parseInt(this.value)||2)">
            <div class="form-text small" style="color:var(--muted)">Máximo 30 — cada jugador recibirá su PIN por email (obligatorio)</div>
        </div>
        <div id="indiv-email-fields" class="d-flex flex-column gap-2 mb-3"></div>

      </div>

      <button class="btn btn-game btn-lg w-100 rounded-pill fw-bold" onclick="createGame()">
        🎮 Crear Partida
      </button>
      <div class="text-center mt-2">
        <a href="<?= BASE_URL ?>/Vista/songs.php" class="small" style="color:var(--accent)">🎵 Gestionar catálogo de canciones</a>
      </div>
      <div id="setup-error" class="alert alert-danger mt-3 py-2 small d-none"></div>
    </div>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Volver al inicio</a>
    </div>

  </div>
</div>

<!-- ══ WAITING (Lobby) ══ -->
<div id="screen-waiting" class="screen">
  <div class="container py-4 d-flex flex-column align-items-center gap-4" style="max-width:640px">

    <div class="card w-100 p-4 text-center" id="w-pin-card">
      <div class="text-secondary small text-uppercase fw-semibold mb-1">PIN de la partida</div>
      <div class="pin-box" id="w-pin">----</div>
      <div class="d-flex align-items-center justify-content-center gap-2 mt-2 flex-wrap">
        <div class="text-secondary small">
          Jugadores: <strong id="w-player-url"><?= BASE_URL ?>/player</strong>
        </div>
        <button id="btn-copy-pin" onclick="copySharedPin()"
                class="btn btn-sm btn-outline-secondary rounded-pill"
                style="font-size:.75rem;padding:.2rem .65rem">
          📋 Copiar
        </button>
      </div>
    </div>

    <!-- Panel PINs individuales (oculto en modo compartido) -->
    <div class="card w-100 p-4 d-none" id="w-indiv-pins">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-secondary small text-uppercase fw-semibold">Cartones / PINs individuales</div>
        <button class="btn btn-sm btn-outline-secondary rounded-pill" id="btn-copy-all" onclick="copyAllPins()">
          📋 Copiar todos
        </button>
      </div>
      <div class="d-flex flex-wrap gap-2 justify-content-center" id="w-pins-grid"></div>
      <div class="text-secondary small mt-3 text-center">Haz clic en un cartón para copiar su PIN</div>
    </div>

    <div class="w-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-secondary small text-uppercase fw-semibold">Jugadores en la sala</span>
        <span class="badge rounded-pill fw-bold fs-6" style="background:var(--accent)" id="w-count">0</span>
      </div>
      <div id="w-players" class="card d-flex flex-wrap gap-2 justify-content-center p-3" style="min-height:60px"></div>
    </div>

    <div class="d-flex flex-column align-items-center gap-2">
      <button id="btn-start" class="btn btn-success btn-lg rounded-pill px-5 fw-bold" onclick="startGame()" disabled>
        ▶ Iniciar Partida
      </button>
      <span class="text-secondary small">Mínimo 1 jugador para comenzar</span>
    </div>

    <button class="btn btn-outline-secondary btn-sm rounded-pill px-4" onclick="newGame()">‹ Cancelar partida</button>

  </div>
</div>

<!-- ══ QUESTION ══ -->
<div id="screen-question" class="screen">
  <div class="admin-grid">

    <div class="col-main">

      <!-- Barra ronda + timer -->
      <div class="card p-3 d-flex flex-row align-items-center gap-3">
        <div>
          <div class="text-secondary small text-uppercase">Ronda</div>
          <div class="fw-black fs-3"><span id="q-round">1</span>/<span id="q-total">10</span></div>
          <span id="hard-mode-badge" class="d-none mt-1"
                style="display:inline-block;font-size:.7rem;font-weight:700;color:#fff;
                       background:rgba(233,69,96,.85);padding:.15rem .5rem;border-radius:20px;
                       letter-spacing:.03em">🔥 MODO DIFÍCIL</span>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between small text-secondary mb-1">
            <span>Jugadores han respondido</span>
            <span id="q-answered">0</span> / <span id="q-players">0</span>
          </div>
          <div class="answer-progress"><div class="answer-progress-fill" id="q-progress" style="width:0%"></div></div>
        </div>
        <div class="timer-ring">
          <svg viewBox="0 0 80 80" width="76" height="76">
            <circle class="bg" cx="40" cy="40" r="35"/>
            <circle class="fg" id="timer-circle" cx="40" cy="40" r="35"/>
          </svg>
          <span class="timer-number" id="q-timer">30</span>
        </div>
      </div>

      <!-- Canción del turno — el dinamizador la pone físicamente -->
      <div class="round-song-card">
        <div class="rsc-label">🎵 Canción de esta ronda — ponla en el reproductor</div>
        <div class="rsc-title"  id="q-title">—</div>
        <div class="rsc-artist" id="q-artist">—</div>
        <div class="rsc-year year-hidden" id="q-year">—</div>
        <div class="rsc-genre"  id="q-genre"></div>
        <div class="text-secondary small mt-2">El año se desvela al terminar el tiempo</div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="if(confirm('¿Abandonar la partida?')) newGame()">‹ Salir</button>
        <button class="btn btn-game rounded-pill px-4 fw-bold" onclick="showResults()">
          Revelar año y ver resultados →
        </button>
      </div>

    </div>

    <!-- Columna media: streaming + audio -->
    <div class="col-media">
      <div id="q-streaming" class="card p-3 d-none">
        <div class="text-secondary small text-uppercase fw-semibold mb-2">Streaming</div>
        <div id="q-yt-embed" class="mb-2 d-none">
          <div class="ratio ratio-16x9">
            <iframe id="q-yt-iframe" src="" allow="autoplay; encrypted-media" allowfullscreen
                    style="border-radius:8px;border:none"></iframe>
          </div>
        </div>
        <div id="q-stream-btns" class="d-flex gap-2 flex-wrap"></div>
      </div>
      <div class="card p-3 d-none" id="q-audio-card">
        <div class="text-secondary small text-uppercase fw-semibold mb-2">🎵 Audio de la canción</div>
        <audio id="q-audio"></audio>
        <div class="audio-ctrl">
          <button class="a-play" id="q-play" onclick="audioToggle('q')" title="Play/Pausa">
            <svg id="q-play-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <div class="a-bar" id="q-bar" onclick="audioSeek('q',event,this)">
            <div class="a-fill" id="q-afill"></div>
          </div>
          <span class="a-time" id="q-atime">0:00</span>
        </div>
        <div class="audio-vol mt-2">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
          <input type="range" class="audio-vol-range" id="q-vol" min="0" max="100" value="80" oninput="setVolume(this.value)">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
        </div>
      </div>
    </div>

    <div class="col-side">
      <div class="text-secondary text-uppercase small fw-semibold">Clasificación en vivo</div>
      <div id="q-leaderboard" class="leaderboard"></div>
    </div>

  </div>
</div>

<!-- ══ RESULTS ══ -->
<div id="screen-results" class="screen">
  <div class="admin-grid">

    <div class="col-main">

      <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
        <span class="text-secondary small text-uppercase">Resultados — Ronda</span>
        <span class="fw-black fs-5"><span id="r-round">1</span> / <span id="r-total">10</span></span>
      </div>

      <!-- Reveal -->
      <div class="round-song-card">
        <div class="rsc-label">✅ Respuesta correcta</div>
        <div class="rsc-title"  id="r-title">—</div>
        <div class="rsc-artist" id="r-artist">—</div>
        <div class="rsc-year year-revealed" id="r-year">—</div>
        <div class="rsc-genre"  id="r-genre"></div>
      </div>

      <div>
        <div class="text-secondary text-uppercase small fw-semibold mb-2">Quién acertó</div>
        <div id="r-results"></div>
      </div>

      <div class="mt-auto d-flex justify-content-between align-items-center pb-3">
        <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="if(confirm('¿Abandonar la partida?')) newGame()">‹ Salir</button>
        <button class="btn btn-game btn-lg rounded-pill px-5 fw-bold" id="btn-next" onclick="nextRound()">
          Siguiente Ronda →
        </button>
      </div>

    </div>

    <!-- Columna media: audio + links -->
    <div class="col-media">
      <div class="card p-3 d-none" id="r-audio-card">
        <div class="text-secondary small text-uppercase fw-semibold mb-2">🎵 Audio de la canción</div>
        <audio id="r-audio"></audio>
        <div class="audio-ctrl">
          <button class="a-play" id="r-play" onclick="audioToggle('r')" title="Play/Pausa">
            <svg id="r-play-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <div class="a-bar" id="r-bar" onclick="audioSeek('r',event,this)">
            <div class="a-fill" id="r-afill"></div>
          </div>
          <span class="a-time" id="r-atime">0:00</span>
        </div>
        <div class="audio-vol mt-2">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
          <input type="range" class="audio-vol-range" id="r-vol" min="0" max="100" value="80" oninput="setVolume(this.value)">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
        </div>
      </div>
      <div id="r-streaming" class="card p-3 d-none">
        <div class="text-secondary small text-uppercase fw-semibold mb-2">🔗 Links</div>
        <div id="r-stream-btns" class="d-flex gap-2 flex-wrap"></div>
      </div>
    </div>

    <div class="col-side">
      <div class="text-secondary text-uppercase small fw-semibold">Clasificación</div>
      <div id="r-leaderboard" class="leaderboard"></div>
    </div>

  </div>
</div>

<!-- ══ FINISHED ══ -->
<div id="screen-finished" class="screen align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column align-items-center gap-4 text-center" style="max-width:560px">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:180px;max-width:100%">
    <h2 class="fw-black display-5 mb-0">🏆 Fin de la partida</h2>
    <div id="f-podium" class="podium w-100"></div>
    <div id="f-leaderboard" class="leaderboard w-100"></div>
    <button class="btn btn-outline-secondary rounded-pill px-5" onclick="newGame()">↩ Nueva Partida</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const API = '<?= BASE_URL ?>/Controlador/api.php';
  const GK  = 'hitstoric_gid';
  const TK  = 'hitstoric_tok';
</script>
<script src="<?= BASE_URL ?>/assets/js/admin.js?v=35"></script>
</body>
</html>
