<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100">

  <div class="container" style="max-width:440px">

    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/Imagenes/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
    </div>

    <div class="d-flex flex-column gap-3 mb-4">

      <a href="<?= BASE_URL ?>/Vista/admin.php" class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3"
        style="border:2px solid transparent !important;transition:border-color .2s,transform .15s"
        onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
        onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">🎛️</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Dinamizador</div>
          <div class="small">Crea la partida y controla cada ronda</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

      <a href="<?= BASE_URL ?>/Vista/player.php" class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3"
        style="border:2px solid transparent !important;transition:border-color .2s,transform .15s"
        onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
        onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">📱</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Jugador</div>
          <div class="small">Únete con el PIN de 4 dígitos</div>
        </div>
        <span class="ms-auto fs-4">›</span>
      </a>

    </div>

    <div class="card p-3">
      <div class="fw-semibold mb-2 small text-uppercase ">Cómo se juega</div>
      <ol class=" small mb-0 ps-3" style="line-height:2">
        <li>Al empezar recibes una carta inicial aleatoria del género de la partida — se muestra con título, artista y año, y es el punto de partida de tu línea del tiempo personal.</li>
        <li>El dinamizador pone una canción. Tienes <strong>tiempo limitado</strong> para decidir dónde encaja en tu línea del tiempo.</li>
        <li>En tu móvil indica si la canción va <strong>antes o después</strong> de las que ya tienes y pulsa <em>Confirmar selección</em>.</li>
        <li>Si aciertas, la canción se añade a tu línea del tiempo y sumas puntos — cuanto más rápido respondas, más puntos de bonus.</li>
        <li>Si fallas, no sumas puntos y la canción no se añade a tu línea del tiempo.</li>
      </ol>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>