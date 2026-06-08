<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Hitster Músicos — Jugador</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <style>
    /* Pantalla de pregunta: sin scroll, ocupa toda la pantalla */
    #screen-question {
      height: 100vh;
      overflow: hidden;
      justify-content: space-between;
    }
    .q-song-block {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      text-align: center;
    }
    .answers-wrap { padding: .75rem; }
  </style>
</head>
<body>

<!-- ══════════════════════════ JOIN ══════════════════════════ -->
<div id="screen-join" class="screen active align-items-center justify-content-center">
  <div class="container py-4" style="max-width:380px">

    <div class="text-center mb-4">
      <h1 class="fw-black fs-1">🎵 Hitster<span style="color:var(--game-accent)">Músicos</span></h1>
    </div>

    <div class="card border-0 p-4">
      <div class="mb-3">
        <label class="form-label text-secondary small fw-semibold text-uppercase">PIN de la partida</label>
        <input id="pin-input" type="text" class="form-control form-control-lg text-center fw-black"
               inputmode="numeric" pattern="[0-9]*" maxlength="4"
               placeholder="· · · ·" autocomplete="off"
               style="font-size:2.5rem;letter-spacing:.3em">
      </div>
      <div class="mb-4">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Tu nombre</label>
        <input id="name-input" type="text" class="form-control form-control-lg"
               placeholder="¿Cómo te llamas?" maxlength="30" autocomplete="off">
      </div>
      <button class="btn btn-game btn-lg w-100 rounded-pill fw-bold" onclick="joinGame()">
        Unirse →
      </button>
      <div id="join-error" class="alert alert-danger mt-3 py-2 d-none text-center small"></div>
    </div>

  </div>
</div>

<!-- ══════════════════════════ LOBBY ══════════════════════════ -->
<div id="screen-lobby" class="screen align-items-center justify-content-center">
  <div class="text-center d-flex flex-column align-items-center gap-3">

    <div id="lobby-avatar"
         style="width:90px;height:90px;border-radius:50%;font-size:2.5rem;font-weight:900;color:#fff;
                display:flex;align-items:center;justify-content:center;text-shadow:0 2px 4px rgba(0,0,0,.4)">
      ?
    </div>

    <div class="fw-black fs-3" id="lobby-name">—</div>

    <div class="text-secondary">
      Esperando al dinamizador
      <span class="waiting-dots"><span>.</span><span>.</span><span>.</span></span>
    </div>

    <div class="card border-0 px-5 py-3 text-center mt-2">
      <div class="text-secondary small mb-1">Jugadores en la sala</div>
      <div class="fw-black" style="font-size:2.5rem;color:var(--game-accent)" id="lobby-count">0</div>
    </div>

  </div>
</div>

<!-- ══════════════════════════ QUESTION ══════════════════════════ -->
<div id="screen-question" class="screen">

  <!-- Barra superior: ronda + timer -->
  <div class="d-flex align-items-center gap-3 px-3 py-2" style="background:rgba(255,255,255,.04)">
    <div>
      <div class="text-secondary" style="font-size:.7rem;text-transform:uppercase">Ronda</div>
      <div class="fw-bold"><span id="q-round">1</span>/<span id="q-total">10</span></div>
    </div>
    <div class="flex-grow-1">
      <div class="timer-bar"><div class="timer-fill" id="timer-fill" style="width:100%"></div></div>
    </div>
    <div class="fw-black fs-5" id="q-secs" style="color:var(--game-accent);min-width:28px;text-align:right">20</div>
  </div>

  <!-- Canción -->
  <div class="q-song-block">
    <div class="fw-black mb-1" style="font-size:1.65rem;line-height:1.2" id="q-title">—</div>
    <div class="text-secondary fs-5 mb-3" id="q-artist">—</div>
    <div class="badge text-secondary" style="background:rgba(255,255,255,.06);font-size:.8rem">
      ¿En qué año se lanzó esta canción?
    </div>
  </div>

  <!-- Botones de respuesta -->
  <div class="answers-wrap">
    <div class="answers-grid">
      <button class="answer-btn ans-a" id="btn-0" onclick="submitAnswer(0)"></button>
      <button class="answer-btn ans-b" id="btn-1" onclick="submitAnswer(1)"></button>
      <button class="answer-btn ans-c" id="btn-2" onclick="submitAnswer(2)"></button>
      <button class="answer-btn ans-d" id="btn-3" onclick="submitAnswer(3)"></button>
    </div>
  </div>

</div>

<!-- ══════════════════════════ ANSWERED (esperando) ══════════════════════════ -->
<div id="screen-answered" class="screen align-items-center justify-content-center gap-3">
  <div class="game-spinner"></div>
  <div class="fw-semibold fs-5">Respuesta enviada</div>
  <div class="text-secondary small">Esperando a los demás jugadores…</div>
</div>

<!-- ══════════════════════════ RESULTS ══════════════════════════ -->
<div id="screen-results" class="screen align-items-center py-4 gap-3">
  <div class="container d-flex flex-column align-items-center gap-3" style="max-width:400px">

    <!-- Feedback -->
    <div style="font-size:4.5rem" id="res-icon">—</div>
    <div class="fw-black fs-4 text-center" id="res-msg">—</div>
    <div class="pts-badge" id="res-pts"></div>

    <!-- Canción reveal -->
    <div class="card border-0 w-100 p-3 text-center">
      <div class="text-secondary small mb-1">La respuesta correcta era</div>
      <div class="fw-bold fs-5" id="r-title">—</div>
      <div class="text-secondary" id="r-artist">—</div>
      <div class="fw-black mt-1" style="font-size:1.75rem;color:var(--game-accent)" id="r-year">—</div>
    </div>

    <!-- Ranking -->
    <div class="text-secondary small" id="res-rank">Posición — / —</div>

    <!-- Mini leaderboard -->
    <div id="res-leaderboard" class="leaderboard w-100"></div>

  </div>
</div>

<!-- ══════════════════════════ FINISHED ══════════════════════════ -->
<div id="screen-finished" class="screen align-items-center justify-content-center py-4">
  <div class="container d-flex flex-column align-items-center gap-3 text-center" style="max-width:400px">

    <h2 class="fw-black display-5">🏆 ¡Fin!</h2>
    <div class="fw-bold fs-5" id="f-rank"></div>
    <div class="pts-badge" id="f-score"></div>
    <div id="f-leaderboard" class="leaderboard w-100"></div>

    <button class="btn btn-outline-secondary rounded-pill px-5 mt-2" onclick="goToJoin()">
      Jugar de nuevo
    </button>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const API = '<?= BASE_URL ?>/Controlador/api.php';
  const PK  = 'hitster_pid';
  const GK  = 'hitster_gid_p';
</script>
<script src="<?= BASE_URL ?>/assets/js/player.js"></script>
</body>
</html>
