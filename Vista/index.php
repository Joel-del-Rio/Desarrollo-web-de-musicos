<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitster Músicos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

  <div class="container" style="max-width:460px">

    <!-- Logo -->
    <div class="text-center mb-4">
      <h1 class="fw-black fs-1 mb-1">
        🎵 Hitster<span style="color:var(--game-accent)">Músicos</span>
      </h1>
      <p class="text-secondary">El juego musical en tiempo real.<br>¿De qué año es esa canción?</p>
    </div>

    <!-- Tarjetas de rol -->
    <div class="d-flex flex-column gap-3 mb-4">

      <a href="admin.php" class="card text-decoration-none border-0 p-3 d-flex flex-row align-items-center gap-3"
         style="transition:transform .15s,border-color .2s;border:2px solid transparent !important;"
         onmouseover="this.style.borderColor='var(--game-accent)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">🎛️</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Soy el Dinamizador</div>
          <div class="text-secondary small">Crea y controla la partida desde esta pantalla grande</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

      <a href="player.php" class="card text-decoration-none border-0 p-3 d-flex flex-row align-items-center gap-3"
         style="transition:transform .15s,border-color .2s;border:2px solid transparent !important;"
         onmouseover="this.style.borderColor='var(--game-accent)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">📱</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Soy Jugador</div>
          <div class="text-secondary small">Únete con el PIN de 4 dígitos desde tu móvil</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

    </div>

    <p class="text-center text-secondary" style="font-size:.75rem">
      Primera vez: importa <code>db_setup.sql</code> en phpMyAdmin antes de jugar.
    </p>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
