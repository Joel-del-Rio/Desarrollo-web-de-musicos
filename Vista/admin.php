<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Dinamizador</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>

<!-- ══ SETUP ══ -->
<div id="screen-setup" class="screen active align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column" style="max-width:500px">

    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/Imagenes/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
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
        <input type="range" class="form-range" id="time-input" min="10" max="60" value="30" step="5"
               oninput="document.getElementById('time-display').textContent=this.value">
        <div class="d-flex justify-content-between text-secondary small"><span>10s</span><span>60s</span></div>
      </div>

      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Género musical</label>
        <div class="d-flex flex-wrap gap-2 mt-2" id="genre-selector">
          <?php
          $genres = ['Todos','Rock Internacional','Pop/Rock Español','80s','New Age',
                     'Rock en Español','Trap/Rap Internacional','Trap/Rap en Español','Actualidad'];
          foreach ($genres as $g):
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

      <!-- Opciones de streaming -->
      <div class="mb-4">
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

        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary border-opacity-25" id="row-embed">
          <div>
            <div class="fw-semibold small">YouTube embebido</div>
            <div class="small" style="color:var(--muted);opacity:.75">Reproduce el vídeo dentro de la app</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-embed" role="switch"
                   onchange="onEmbedToggle()">
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between py-2" id="row-autoplay" style="display:none!important">
          <div>
            <div class="fw-semibold small">Autoplay al cambiar ronda</div>
            <div class="small" style="color:var(--muted);opacity:.75">El vídeo empieza solo al iniciar cada ronda</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-autoplay" role="switch">
          </div>
        </div>
      </div>

      <button class="btn btn-game btn-lg w-100 rounded-pill fw-bold" onclick="createGame()">
        🎮 Crear Partida
      </button>
      <div class="text-center mt-2">
        <a href="songs.php" class="small" style="color:var(--accent)">🎵 Gestionar catálogo de canciones</a>
      </div>
      <div id="setup-error" class="alert alert-danger mt-3 py-2 small d-none"></div>
    </div>

    <div class="text-center mt-3">
      <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Volver al inicio</a>
    </div>

  </div>
</div>

<!-- ══ WAITING (Lobby) ══ -->
<div id="screen-waiting" class="screen">
  <div class="container py-4 d-flex flex-column align-items-center gap-4" style="max-width:640px">

    <div class="card w-100 p-4 text-center">
      <div class="text-secondary small text-uppercase fw-semibold mb-1">PIN de la partida</div>
      <div class="pin-box" id="w-pin">----</div>
      <div class="text-secondary small mt-2">
        Jugadores: <strong><?= BASE_URL ?>/Vista/player.php</strong>
      </div>
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
        <!-- Zona streaming (question) -->
        <div id="q-streaming" class="mt-3 d-none">
          <div id="q-yt-embed" class="mb-2 d-none">
            <div class="ratio ratio-16x9" style="max-height:220px">
              <iframe id="q-yt-iframe" src="" allow="autoplay; encrypted-media" allowfullscreen
                      style="border-radius:8px;border:none"></iframe>
            </div>
          </div>
          <div id="q-stream-btns" class="d-flex gap-2 justify-content-center flex-wrap"></div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="if(confirm('¿Abandonar la partida?')) newGame()">‹ Salir</button>
        <button class="btn btn-game rounded-pill px-4 fw-bold" onclick="showResults()">
          Revelar año y ver resultados →
        </button>
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
        <!-- Zona streaming (results) -->
        <div id="r-streaming" class="mt-3 d-none">
          <div id="r-stream-btns" class="d-flex gap-2 justify-content-center flex-wrap"></div>
        </div>
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

    <div class="col-side">
      <div class="text-secondary text-uppercase small fw-semibold">Clasificación</div>
      <div id="r-leaderboard" class="leaderboard"></div>
    </div>

  </div>
</div>

<!-- ══ FINISHED ══ -->
<div id="screen-finished" class="screen align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column align-items-center gap-4 text-center" style="max-width:560px">
    <img src="<?= BASE_URL ?>/Imagenes/Logo.png" alt="Hitstoric" style="width:180px;max-width:100%">
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
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
