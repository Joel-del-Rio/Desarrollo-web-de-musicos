<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cronófono</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

  <div class="container" style="max-width:440px">

    <div class="text-center mb-5">
      <div style="font-size:3.5rem">🎵</div>
      <h1 class="fw-black display-4 mb-1">Cronófono</h1>
      <p class="text-secondary">Ordena las canciones en el tiempo.<br>¿Sabes de qué época es cada hit?</p>
    </div>

    <div class="d-flex flex-column gap-3 mb-4">

      <a href="admin.php" class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3"
         style="border:2px solid transparent !important;transition:border-color .2s,transform .15s"
         onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">🎛️</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Dinamizador</div>
          <div class="text-secondary small">Crea la partida y controla cada ronda</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

      <a href="player.php" class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3"
         style="border:2px solid transparent !important;transition:border-color .2s,transform .15s"
         onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">📱</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Jugador</div>
          <div class="text-secondary small">Únete con el PIN de 4 dígitos</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

    </div>

    <div class="card p-3">
      <div class="fw-semibold mb-2 small text-uppercase text-secondary">Cómo se juega</div>
      <ol class="text-secondary small mb-0 ps-3" style="line-height:2">
        <li>Cada jugador recibe una canción inicial como ancla de su línea del tiempo</li>
        <li>El dinamizador pone una canción físicamente (tarjeta o Spotify)</li>
        <li>Cada jugador la coloca en su línea del tiempo en el lugar correcto</li>
        <li>Acertar el orden cronológico suma puntos y añade la canción al timeline</li>
      </ol>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
