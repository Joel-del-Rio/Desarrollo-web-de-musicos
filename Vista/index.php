<?php
/**
 * index.php — Página de inicio
 *
 * Muestra las tres opciones de navegación principales:
 * Dinamizador, Jugador y Premios, junto con las instrucciones del juego.
 */
require_once __DIR__ . '/../config.php'; ?>
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
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
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

      <a href="<?= BASE_URL ?>/Vista/premios.php" class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3"
        style="border:2px solid transparent !important;transition:border-color .2s,transform .15s"
        onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
        onmouseout="this.style.borderColor='transparent';this.style.transform=''">
        <span style="font-size:2.5rem">🏆</span>
        <div>
          <div class="fw-bold fs-5 text-white mb-1">Premios</div>
          <div class="small">Consulta la clasificación y los premios de una partida</div>
        </div>
        <span class="ms-auto text-secondary fs-4">›</span>
      </a>

    </div>

    <!-- Tarjeta superadmin: visible solo tras login -->
    <a href="<?= BASE_URL ?>/Vista/superadmin.php" id="superadmin-card"
       class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3 d-none mt-3 mb-3"
       style="border:2px solid rgba(233,69,96,.4) !important;transition:border-color .2s,transform .15s"
       onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
       onmouseout="this.style.borderColor='rgba(233,69,96,.4)';this.style.transform=''">
      <span style="font-size:2.5rem">🔐</span>
      <div>
        <div class="fw-bold fs-5 text-white mb-1">Superadmin</div>
        <div class="small">Panel de supervisión global de partidas</div>
      </div>
      <span class="ms-auto text-secondary fs-4">›</span>
    </a>

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

    <!-- Botón oculto de acceso superadmin — debajo de la caja de normas -->
    <div id="sa-btn-wrap" class="text-center mt-3 pb-4">
    <button onclick="document.getElementById('saLoginModal').style.display='flex'"
            style="background:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;
                   color:rgba(255,255,255,.25);font-size:.78rem;font-weight:600;padding:.3rem .6rem;
                   border-radius:6px;transition:color .2s"
            onmouseover="this.style.color='rgba(255,255,255,.5)'"
            onmouseout="this.style.color='rgba(255,255,255,.25)'">
      🔒 Admin
    </button>
    </div>

  </div>

  <!-- Modal login superadmin -->
  <div id="saLoginModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
    <div class="card p-4" style="width:100%;max-width:340px">
      <div class="fw-black mb-3">Acceso Superadmin</div>
      <div class="mb-3">
        <label class="form-label small text-secondary fw-semibold text-uppercase">Email</label>
        <input type="email" id="idx-sa-email" class="form-control" autocomplete="email">
      </div>
      <div class="mb-3">
        <label class="form-label small text-secondary fw-semibold text-uppercase">Contraseña</label>
        <input type="password" id="idx-sa-pass" class="form-control" autocomplete="current-password"
               onkeydown="if(event.key==='Enter')idxSaLogin()">
      </div>
      <div id="idx-sa-err" class="alert alert-danger py-2 small d-none mb-3"></div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary rounded-pill flex-fill"
                onclick="document.getElementById('saLoginModal').style.display='none'">Cancelar</button>
        <button class="btn btn-game rounded-pill flex-fill fw-bold" onclick="idxSaLogin()">Entrar</button>
      </div>
    </div>
  </div>

  <script>
    const _API = '<?= BASE_URL ?>/Controlador/api.php';

    // Mostrar tarjeta superadmin si ya autenticado en esta sesión
    if (sessionStorage.getItem('sa_auth') === '1') {
      document.getElementById('superadmin-card').classList.remove('d-none');
      document.getElementById('sa-btn-wrap').classList.add('d-none');
    }

    async function idxSaLogin() {
      const email = document.getElementById('idx-sa-email').value.trim();
      const pass  = document.getElementById('idx-sa-pass').value;
      const errEl = document.getElementById('idx-sa-err');
      errEl.classList.add('d-none');

      const r = await fetch(`${_API}?action=superadmin_login`, {
        method: 'POST',
        body: new URLSearchParams({ email, password: pass }),
      }).then(r => r.json()).catch(() => ({ error: 'Error de conexión' }));

      if (r.success) {
        sessionStorage.setItem('sa_auth', '1');
        document.getElementById('saLoginModal').style.display = 'none';
        document.getElementById('superadmin-card').classList.remove('d-none');
        document.getElementById('sa-btn-wrap').classList.add('d-none');
      } else {
        errEl.textContent = r.error || 'Credenciales incorrectas';
        errEl.classList.remove('d-none');
        document.getElementById('idx-sa-pass').value = '';
        document.getElementById('idx-sa-pass').focus();
      }
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>