<?php
/**
 * player.php — Vista del jugador (móvil)
 *
 * SPA optimizada para móvil con altura dinámica (dvh) y sin zoom.
 * Pantallas en orden: join → lobby → question → answered → results → finished.
 * El JS (player.js) gestiona las transiciones mediante polling al servidor.
 *
 * Constantes JS exportadas al script:
 *   API = URL de la API (Controlador/api.php)
 *   PK  = clave localStorage para el ID del jugador
 *   GK  = clave localStorage para el ID de la partida
 */
require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Hitstoric — Jugador</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
  <style>
    #screen-question {
      height: 100dvh; /* dynamic viewport height (iOS safe) */
      overflow: hidden;
    }
    .timeline-scroll {
      flex: 1;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
  </style>
</head>
<body>

<!-- ══ JOIN ══ -->
<div id="screen-join" class="screen active align-items-center justify-content-center">
  <div class="container py-4 d-flex flex-column" style="max-width:380px">

    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
    </div>

    <div class="text-center mb-3" style="color:var(--muted);font-size:.88rem;line-height:1.6">
      Ordena canciones por año antes que los demás.<br>
      <span style="color:rgba(255,255,255,.5);font-size:.78rem">Pide el PIN de 4 dígitos al dinamizador.</span>
    </div>

    <div class="card p-4">
      <div class="mb-3">
        <label class="form-label text-secondary small fw-semibold text-uppercase">PIN de la partida</label>
        <input id="pin-input" type="text" class="form-control form-control-lg text-center fw-black"
               inputmode="numeric" pattern="[0-9]*" maxlength="4"
               placeholder="· · · ·" autocomplete="off"
               style="font-size:2.5rem;letter-spacing:.3em">
      </div>
      <div class="mb-3">
        <label class="form-label text-secondary small fw-semibold text-uppercase">Tu nombre</label>
        <input id="name-input" type="text" class="form-control form-control-lg"
               placeholder="¿Cómo te llamas?" maxlength="30" autocomplete="off">
      </div>
      <button class="btn btn-game btn-lg w-100 rounded-pill fw-bold" onclick="joinGame()">
        Entrar a la partida →
      </button>
      <div id="join-error" class="alert alert-danger mt-3 py-2 small d-none text-center"></div>
    </div>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Volver al inicio</a>
    </div>

  </div>
</div>

<!-- ══ LOBBY ══ -->
<div id="screen-lobby" class="screen align-items-center justify-content-center gap-3">
  <div id="lobby-avatar"
       style="width:90px;height:90px;border-radius:50%;font-size:2.5rem;font-weight:900;color:#fff;
              display:flex;align-items:center;justify-content:center;text-shadow:0 2px 4px rgba(0,0,0,.4)">?</div>
  <div class="fw-black fs-3" id="lobby-name">—</div>
  <p class="text-secondary mb-0">
    Esperando al dinamizador
    <span class="waiting-dots"><span>.</span><span>.</span><span>.</span></span>
  </p>
  <div class="card px-5 py-3 text-center">
    <div class="text-secondary small mb-1">Jugadores en la sala</div>
    <div class="fw-black" style="font-size:2.5rem;color:var(--accent)" id="lobby-count">0</div>
  </div>
  <div class="card px-4 py-3 text-center" style="max-width:300px;background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08) !important">
    <div class="small" style="color:var(--muted);line-height:1.7">
      🎵 Sonarán canciones una a una.<br>
      📅 Colócalas en orden cronológico.<br>
      ⚡ Acierta rápido para ganar más puntos.
    </div>
  </div>
  <button class="btn btn-outline-secondary btn-sm rounded-pill px-4" onclick="goToJoin()">‹ Salir</button>
</div>

<!-- ══ QUESTION (timeline) ══ -->
<div id="screen-question" class="screen">

  <!-- Canción nueva (cabecera sticky) -->
  <div class="new-song-card">
    <div class="d-flex align-items-start justify-content-between gap-2">
      <div style="flex:1;min-width:0">
        <div class="ns-label">🎵 ¿En qué año salió? Colócala en tu línea del tiempo</div>
        <div class="ns-title"  id="q-title">—</div>
        <div class="ns-artist" id="q-artist">—</div>
      </div>
      <div class="text-end" style="flex-shrink:0">
        <div class="fw-black" style="font-size:2rem;color:var(--accent)" id="q-secs">30</div>
        <div class="text-secondary" style="font-size:.7rem">seg</div>
      </div>
    </div>
    <div class="timer-bar mt-2"><div class="timer-fill" id="timer-fill" style="width:100%"></div></div>
    <div class="d-flex align-items-center justify-content-between mt-1">
      <div class="text-secondary" style="font-size:.75rem">
        Ronda <span id="q-round">1</span> de <span id="q-total">10</span>
      </div>
      <div id="q-streak-box" class="d-none" style="font-size:.75rem;font-weight:700;color:var(--accent)">
        🔥 Racha <span id="q-streak-count">0</span> &nbsp;×<span id="q-multiplier">1.0</span>
      </div>
    </div>
  </div>

  <!-- Reproductor de audio (iTunes preview; visible solo si el admin activó embed_youtube) -->
  <div id="audio-section" class="d-none" style="flex-shrink:0;background:var(--bg2);border-bottom:1px solid var(--bs-border-color);padding:.6rem 1rem .5rem">
    <audio id="p-audio"></audio>
    <div class="audio-ctrl">
      <button class="a-play" id="p-play" onclick="playerAudioToggle()" title="Play/Pausa">
        <svg id="p-play-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <div class="a-bar" id="p-bar" onclick="playerAudioSeek(event,this)">
        <div class="a-fill" id="p-afill"></div>
      </div>
      <span class="a-time" id="p-atime">0:00</span>
    </div>
    <div class="audio-vol mt-1">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
      <input type="range" class="audio-vol-range" id="p-vol" min="0" max="100" value="80" oninput="playerSetVolume(this.value)">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="opacity:.5;flex-shrink:0"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
    </div>
    <div id="audio-links" class="d-flex gap-2 mt-2 flex-wrap"></div>
  </div>

  <!-- Timeline scrollable -->
  <div class="timeline-scroll" id="timeline-area">
    <!-- Generado por JS -->
  </div>

  <!-- Barra de confirmación -->
  <div class="confirm-bar">
    <button id="confirm-btn" class="btn btn-game btn-lg w-100 rounded-pill fw-bold" disabled onclick="confirmAnswer()">
      Confirmar posición
    </button>
    <div class="d-flex align-items-center justify-content-between mt-2">
      <div class="text-secondary" style="font-size:.75rem" id="confirm-hint">
        👆 Toca «Antes» o «Después» para colocarla
      </div>
      <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" style="font-size:.72rem"
              onclick="leaveGame()">Salir</button>
    </div>
  </div>

</div>

<!-- ══ ANSWERED ══ -->
<div id="screen-answered" class="screen align-items-center justify-content-center gap-3">
  <div class="game-spinner"></div>
  <div class="fw-semibold fs-5">Respuesta enviada</div>
  <div class="text-secondary small">Esperando a los demás jugadores…</div>
  <button class="btn btn-outline-secondary btn-sm rounded-pill px-4 mt-2" onclick="leaveGame()">‹ Salir</button>
</div>

<!-- ══ RESULTS ══ -->
<div id="screen-results" class="screen align-items-center py-4">
  <div class="container d-flex flex-column align-items-center gap-3 text-center" style="max-width:420px">

    <div style="font-size:4.5rem" id="res-icon">—</div>
    <div class="fw-black fs-3" id="res-msg">—</div>
    <div class="pts-badge" id="res-pts"></div>
    <div id="res-streak" class="d-none fw-bold" style="font-size:.95rem;color:var(--accent)"></div>

    <!-- Reveal canción -->
    <div class="card w-100 p-3">
      <div class="fw-bold fs-5" id="r-title">—</div>
      <div class="text-secondary"    id="r-artist">—</div>
      <div class="fw-black mt-1" style="font-size:2.5rem;color:var(--accent)" id="r-year">—</div>
      <div class="text-secondary small" id="r-genre"></div>
    </div>

    <div class="text-secondary small" id="res-rank"></div>

    <!-- Mini leaderboard -->
    <div id="res-leaderboard" class="leaderboard w-100"></div>

    <!-- Timeline actualizado -->
    <div class="w-100 text-start">
      <div class="text-secondary small text-uppercase fw-semibold mb-2">Tu línea del tiempo actualizada</div>
      <div id="res-timeline"></div>
    </div>

    <button class="btn btn-outline-secondary btn-sm rounded-pill px-4 mt-1" onclick="leaveGame()">‹ Salir</button>

  </div>
</div>

<!-- ══ FINISHED ══ -->
<div id="screen-finished" class="screen align-items-center justify-content-center py-4">
  <div class="container d-flex flex-column align-items-center gap-3 text-center" style="max-width:400px">
    <h2 class="fw-black display-5">🏆 ¡Fin!</h2>
    <div class="fw-bold fs-5" id="f-rank"></div>
    <div class="pts-badge"    id="f-score"></div>
    <div id="f-prize" class="d-none w-100 text-center py-2 px-3 rounded-3 fw-bold" style="background:rgba(233,69,96,.15);border:2px solid var(--accent);font-size:1.05rem"></div>
    <div id="f-leaderboard" class="leaderboard w-100"></div>
    <button class="btn btn-game rounded-pill px-5 mt-2 fw-bold" onclick="goToJoin()">🎮 Jugar de nuevo</button>
    <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary rounded-pill px-5">‹ Inicio</a>
  </div>
</div>

<!-- DBG:v25 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const API = '<?= BASE_URL ?>/Controlador/api.php';
  const PK  = 'hitstoric_pid';
  const GK  = 'hitstoric_gid_p';
</script>
<script src="<?= BASE_URL ?>/assets/js/player.js?v=33"></script>
</body>
</html>
