<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitster Músicos — Dinamizador</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <style>
    .admin-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1.25rem; padding: 1.25rem; height: 100vh; overflow: hidden; }
    .admin-grid .col-main, .admin-grid .col-side { display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; }
    @media (max-width: 900px) { .admin-grid { grid-template-columns: 1fr; height: auto; overflow: auto; } }
  </style>
</head>
<body>

<!-- ══════════════════════════ SETUP ══════════════════════════ -->
<div id="screen-setup" class="screen active align-items-center justify-content-center">
  <div class="container" style="max-width:480px">

    <div class="text-center mb-4">
      <h1 class="fw-black fs-1">🎵 Hitster<span style="color:var(--game-accent)">Músicos</span></h1>
      <p class="text-secondary">Panel del Dinamizador</p>
    </div>

    <div class="card border-0 p-4">
      <h5 class="fw-bold mb-4">Configurar partida</h5>

      <label class="form-label text-secondary small text-uppercase fw-semibold">Número de rondas</label>
      <div class="text-center" style="font-size:3.5rem;font-weight:900;color:var(--game-accent);line-height:1" id="rounds-display">10</div>
      <input type="range" class="form-range my-3" id="rounds-input" min="5" max="20" value="10"
             oninput="document.getElementById('rounds-display').textContent=this.value">
      <div class="d-flex justify-content-between text-secondary small mb-4">
        <span>5 rondas</span><span>20 rondas</span>
      </div>

      <button class="btn btn-game btn-lg w-100 rounded-pill" onclick="createGame()">
        🎮 Crear Partida
      </button>
      <div id="setup-error" class="alert alert-danger mt-3 py-2 d-none"></div>
    </div>

  </div>
</div>

<!-- ══════════════════════════ WAITING (Lobby) ══════════════════════════ -->
<div id="screen-waiting" class="screen">
  <div class="container py-4 d-flex flex-column align-items-center gap-4" style="max-width:640px">

    <!-- PIN -->
    <div class="card border-0 w-100 text-center p-4">
      <div class="text-secondary text-uppercase small fw-semibold mb-1 ls-wider">PIN de la partida</div>
      <div class="pin-box" id="w-pin">----</div>
      <div class="text-secondary small mt-2">
        Jugadores: <strong><?= BASE_URL ?>/Vista/player.php</strong>
      </div>
    </div>

    <!-- Jugadores -->
    <div class="w-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-secondary text-uppercase small fw-semibold">Jugadores en la sala</span>
        <span class="badge rounded-pill" style="background:var(--game-accent)" id="w-count">0</span>
      </div>
      <div id="w-players" class="d-flex flex-wrap gap-2 justify-content-center p-3 card border-0" style="min-height:60px"></div>
    </div>

    <!-- Botón iniciar -->
    <div class="d-flex flex-column align-items-center gap-2">
      <button id="btn-start" class="btn btn-success btn-lg rounded-pill px-5 fw-bold" onclick="startGame()" disabled>
        ▶ Iniciar Partida
      </button>
      <span class="text-secondary small">Mínimo 1 jugador para comenzar</span>
    </div>

  </div>
</div>

<!-- ══════════════════════════ QUESTION ══════════════════════════ -->
<div id="screen-question" class="screen">
  <div class="admin-grid">

    <!-- Columna principal -->
    <div class="col-main">

      <!-- Barra superior -->
      <div class="card border-0 p-3 d-flex flex-row align-items-center gap-3">
        <div>
          <div class="text-secondary small text-uppercase">Ronda</div>
          <div class="fw-black fs-4"><span id="q-round">1</span> / <span id="q-total">10</span></div>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between small text-secondary mb-1">
            <span>Respuestas recibidas</span>
            <span><span id="q-answered">0</span> / <span id="q-players">0</span></span>
          </div>
          <div class="answer-progress"><div class="answer-progress-fill" id="q-progress" style="width:0%"></div></div>
        </div>
        <div class="timer-ring">
          <svg viewBox="0 0 80 80" width="76" height="76">
            <circle class="bg" cx="40" cy="40" r="35"/>
            <circle class="fg" id="timer-circle" cx="40" cy="40" r="35"/>
          </svg>
          <span class="timer-number" id="q-timer">20</span>
        </div>
      </div>

      <!-- Canción -->
      <div class="card border-0 p-4 text-center flex-grow-1 d-flex flex-column justify-content-center">
        <div class="text-secondary small mb-2">🎵 ¿De qué año es esta canción?</div>
        <div class="fw-black mb-1" style="font-size:2rem" id="q-title">—</div>
        <div class="text-secondary fs-5" id="q-artist">—</div>
      </div>

      <div class="text-end">
        <button class="btn btn-game rounded-pill px-4 fw-bold" onclick="showResults()">
          Ver Resultados →
        </button>
      </div>

    </div><!-- /col-main -->

    <!-- Columna lateral -->
    <div class="col-side">
      <div class="text-secondary text-uppercase small fw-semibold">Clasificación en vivo</div>
      <div id="q-leaderboard" class="leaderboard"></div>
    </div>

  </div>
</div>

<!-- ══════════════════════════ RESULTS ══════════════════════════ -->
<div id="screen-results" class="screen">
  <div class="admin-grid">

    <!-- Columna principal -->
    <div class="col-main">

      <div class="card border-0 p-3 d-flex flex-row align-items-center justify-content-between">
        <div class="text-secondary small text-uppercase">Resultados — Ronda</div>
        <div class="fw-black fs-5"><span id="r-round">1</span> / <span id="r-total">10</span></div>
      </div>

      <!-- Reveal canción -->
      <div class="card border-0 p-4 text-center">
        <div class="fw-black mb-1" style="font-size:1.75rem" id="r-title">—</div>
        <div class="text-secondary fs-5 mb-2" id="r-artist">—</div>
        <div class="fw-black" style="font-size:3rem;color:var(--game-accent)" id="r-year">—</div>
        <div class="text-secondary small" id="r-genre"></div>
      </div>

      <!-- Lista respuestas -->
      <div>
        <div class="text-secondary text-uppercase small fw-semibold mb-2">Respuestas</div>
        <div id="r-results"></div>
      </div>

      <div class="mt-auto text-center pb-3">
        <button class="btn btn-game btn-lg rounded-pill px-5 fw-bold" id="btn-next" onclick="nextRound()">
          Siguiente Ronda →
        </button>
      </div>

    </div><!-- /col-main -->

    <!-- Lateral -->
    <div class="col-side">
      <div class="text-secondary text-uppercase small fw-semibold">Clasificación</div>
      <div id="r-leaderboard" class="leaderboard"></div>
    </div>

  </div>
</div>

<!-- ══════════════════════════ FINISHED ══════════════════════════ -->
<div id="screen-finished" class="screen align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column align-items-center gap-4" style="max-width:560px">

    <h1 class="fw-black display-4">🏆 ¡Partida Terminada!</h1>

    <div id="f-podium" class="podium w-100"></div>

    <div id="f-leaderboard" class="leaderboard w-100"></div>

    <button class="btn btn-outline-secondary rounded-pill px-5" onclick="newGame()">
      ↩ Nueva Partida
    </button>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const API = '<?= BASE_URL ?>/Controlador/api.php';
  const GK  = 'hitster_gid';
  const TK  = 'hitster_tok';
</script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
