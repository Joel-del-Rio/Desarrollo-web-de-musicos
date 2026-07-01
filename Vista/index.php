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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100">

  <div class="container" style="max-width:440px">

    <div class="text-center mb-4">
      <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="width:100%;max-width:100%;display:block">
    </div>

    <div class="d-flex flex-column gap-3 mb-3">

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
       class="card text-decoration-none p-3 d-flex flex-row align-items-center gap-3 d-none mt-0 mb-4"
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
      <div class="fw-semibold mb-3 small text-uppercase">¿Cómo se juega?</div>

      <div class="d-flex flex-column gap-3 small">

        <div class="d-flex gap-3 align-items-start">
          <span style="font-size:1.6rem;flex-shrink:0">🎟️</span>
          <div>
            <div class="fw-bold text-white mb-1">Únete con el PIN</div>
            <div style="color:var(--muted)">El dinamizador te da un código de 4 dígitos. Introdúcelo en la pantalla de jugador para entrar a la partida.</div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <span style="font-size:1.6rem;flex-shrink:0">📅</span>
          <div>
            <div class="fw-bold text-white mb-1">Ordena canciones por año</div>
            <div style="color:var(--muted)">Cada ronda suena una canción nueva. Debes colocarla en tu línea del tiempo <strong style="color:#fff">antes o después</strong> de las que ya tienes, según su año de lanzamiento.</div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <span style="font-size:1.6rem;flex-shrink:0">⚡</span>
          <div>
            <div class="fw-bold text-white mb-1">¡Cuanto más rápido, más puntos!</div>
            <div style="color:var(--muted)">Si aciertas, la canción se queda en tu línea del tiempo y ganas puntos extra por velocidad. Encadenar aciertos activa un <strong style="color:#fff">multiplicador de racha 🔥</strong>.</div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <span style="font-size:1.6rem;flex-shrink:0">❌</span>
          <div>
            <div class="fw-bold text-white mb-1">Si fallas, no se añade</div>
            <div style="color:var(--muted)">La canción no pasa a tu línea del tiempo y pierdes la racha, pero puedes seguir jugando el resto de rondas.</div>
          </div>
        </div>

      </div>

      <button type="button" class="btn btn-game rounded-pill w-100 fw-bold mt-3" onclick="openTutorial()">
        ▶ Probar tutorial interactivo
      </button>
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

  <!-- Modal tutorial interactivo -->
  <div id="tutorialModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9998;align-items:stretch;justify-content:center;overflow-y:auto">
    <div style="width:100%;max-width:420px;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;background:var(--bg);position:relative">

      <div class="d-flex align-items-center justify-content-between p-3" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <div class="fw-black small text-uppercase" style="color:var(--muted)">Tutorial interactivo</div>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="closeTutorial()">✕ Salir</button>
      </div>

      <!-- Bocadillo explicativo -->
      <div class="mx-3 mt-3 p-3 rounded-3" id="tut-explain"
           style="background:rgba(233,69,96,.12);border:1px solid rgba(233,69,96,.35);font-size:.85rem;line-height:1.5">
      </div>

      <!-- Tarjeta de "nueva canción" (simulada) -->
      <div class="new-song-card mx-3 mt-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div style="flex:1;min-width:0">
            <div class="ns-label">🎵 ¿En qué año salió? Colócala en tu línea del tiempo</div>
            <div class="ns-title" id="tut-title">—</div>
            <div class="ns-artist" id="tut-artist">—</div>
          </div>
          <div class="text-end" style="flex-shrink:0">
            <div class="fw-black" style="font-size:1.6rem;color:var(--accent)" id="tut-year-peek">?</div>
            <div class="text-secondary" style="font-size:.65rem">año</div>
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2">
          <div class="text-secondary" style="font-size:.75rem">
            Ronda <span id="tut-round">1</span> de <span id="tut-total">3</span>
          </div>
          <div id="tut-streak-box" class="d-none" style="font-size:.75rem;font-weight:700;color:var(--accent)">
            🔥 Racha <span id="tut-streak-count">0</span> &nbsp;×<span id="tut-multiplier">1.0</span>
          </div>
        </div>
      </div>

      <!-- Timeline simulada -->
      <div class="flex-grow-1 mt-2" id="tut-timeline-area" style="overflow-y:auto"></div>

      <!-- Barra de confirmación -->
      <div class="confirm-bar">
        <button id="tut-confirm-btn" class="btn btn-game btn-lg w-100 rounded-pill fw-bold" disabled onclick="tutConfirm()">
          Confirmar posición
        </button>
        <div class="text-secondary text-center mt-2" style="font-size:.75rem" id="tut-hint">
          👆 Toca «Antes» o «Después» para colocarla
        </div>
      </div>

      <!-- Pantalla de resultado (superpuesta) -->
      <div id="tut-result" class="d-none flex-column align-items-center justify-content-center text-center p-4 gap-2"
           style="position:absolute;inset:0;background:var(--bg)">
        <div style="font-size:3.5rem" id="tut-res-icon">✅</div>
        <div class="fw-black fs-3" id="tut-res-msg">¡Acierto!</div>
        <div class="pts-badge" id="tut-res-pts"></div>
        <div class="small mt-2" style="color:var(--muted);max-width:320px" id="tut-res-explain"></div>
        <button class="btn btn-game rounded-pill px-4 mt-3 fw-bold" onclick="tutNext()" id="tut-res-next">Siguiente →</button>
      </div>

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
    /* ── Tutorial interactivo ─────────────────────────────────────
     * Simula 3 rondas con canciones ficticias para enseñar la mecánica
     * sin necesidad de una partida real. Reutiliza las clases visuales
     * del juego (new-song-card, pos-btn, timeline-song, confirm-bar). */
    const TUT_SONGS = [
      { title: 'Bohemian Rhapsody', artist: 'Queen',        year: 1975 },
      { title: 'Billie Jean',       artist: 'Michael Jackson', year: 1983 },
      { title: 'Rolling in the Deep', artist: 'Adele',      year: 2010 },
    ];
    let tutTimeline   = [];   // canciones ya colocadas (ordenadas por año)
    let tutRound      = 0;    // índice de ronda actual (0-based)
    let tutSelectedPos = null;
    let tutStreak      = 0;

    function openTutorial() {
      tutTimeline = [];
      tutRound = 0;
      tutStreak = 0;
      document.getElementById('tutorialModal').style.display = 'flex';
      tutRenderRound();
    }
    function closeTutorial() {
      document.getElementById('tutorialModal').style.display = 'none';
    }

    function tutRenderRound() {
      document.getElementById('tut-result').classList.add('d-none');
      document.getElementById('tut-result').classList.remove('d-flex');
      tutSelectedPos = null;

      const song = TUT_SONGS[tutRound];
      document.getElementById('tut-title').textContent  = song.title;
      document.getElementById('tut-artist').textContent = song.artist;
      document.getElementById('tut-year-peek').textContent = '?';
      document.getElementById('tut-round').textContent  = tutRound + 1;
      document.getElementById('tut-total').textContent  = TUT_SONGS.length;

      const streakBox = document.getElementById('tut-streak-box');
      if (tutStreak > 0) {
        streakBox.classList.remove('d-none');
        document.getElementById('tut-streak-count').textContent = tutStreak;
        document.getElementById('tut-multiplier').textContent   = (1 + tutStreak * 0.2).toFixed(1);
      } else {
        streakBox.classList.add('d-none');
      }

      const confirmBtn = document.getElementById('tut-confirm-btn');
      confirmBtn.disabled = true;
      confirmBtn.classList.remove('btn-pulse');
      document.getElementById('tut-hint').textContent = tutTimeline.length === 0
        ? '👆 Es tu primera canción — toca el único hueco disponible'
        : '👆 Toca «Antes» o «Después» para colocarla';

      tutBuildTimeline();
      tutExplain();
    }

    function tutExplain() {
      const box = document.getElementById('tut-explain');
      if (tutRound === 0) {
        box.innerHTML = `<strong style="color:#fff">Paso 1 — Tu línea del tiempo está vacía.</strong>
          Suena una canción nueva cada ronda. No ves el año: tienes que adivinar dónde encaja
          respecto a las canciones que ya tienes colocadas. Como es tu primera canción, solo hay un hueco: tócalo.`;
      } else if (tutRound === 1) {
        box.innerHTML = `<strong style="color:#fff">Paso 2 — Compara por año.</strong>
          Ya tienes una canción en tu línea del tiempo. Piensa: ¿esta nueva canción salió
          <em>antes</em> o <em>después</em> que las que ya tienes? Toca el hueco correcto.`;
      } else {
        box.innerHTML = `<strong style="color:#fff">Paso 3 — Cuidado con los fallos.</strong>
          Esta vez vamos a fallar a propósito para que veas qué pasa: la canción no se
          añade a tu línea del tiempo y se pierde la racha 🔥, pero puedes seguir jugando.`;
      }
    }

    function tutBuildTimeline() {
      const area = document.getElementById('tut-timeline-area');
      area.innerHTML = '';
      const n = tutTimeline.length;

      const label = document.createElement('div');
      label.className = 'text-secondary small text-uppercase fw-semibold px-3 pt-2 pb-1';
      label.textContent = 'Tu línea del tiempo — toca dónde encaja';
      area.appendChild(label);

      for (let i = 0; i <= n; i++) {
        const posBtn = document.createElement('button');
        posBtn.className = 'pos-btn';
        posBtn.dataset.pos = i;
        const arrow = i === 0 ? '⬆ Antes de todo' : i === n ? '⬇ Después de todo' : '↕ Aquí';
        posBtn.innerHTML = `<span class="pos-icon">📍</span>${arrow}`;
        posBtn.addEventListener('click', () => tutSelectPosition(i));
        area.appendChild(posBtn);

        if (i < n) {
          const song = tutTimeline[i];
          const card = document.createElement('div');
          card.className = 'timeline-song' + (i === 0 && n === 1 ? ' initial' : '');
          card.innerHTML = `
            <div class="ts-year">${song.year}</div>
            <div class="ts-info">
              <div class="ts-title">${song.title}</div>
              <div class="ts-artist">${song.artist}</div>
            </div>`;
          area.appendChild(card);
        }
      }
    }

    function tutSelectPosition(pos) {
      tutSelectedPos = pos;
      document.querySelectorAll('#tut-timeline-area .pos-btn').forEach(btn => {
        const i = parseInt(btn.dataset.pos);
        const total = document.querySelectorAll('#tut-timeline-area .pos-btn').length;
        const isSelected = i === pos;
        const arrow = i === 0 ? '⬆ Antes de todo' : i === total - 1 ? '⬇ Después de todo' : '↕ Aquí';
        btn.innerHTML = isSelected
          ? `<span class="pos-icon">✅</span>${arrow}`
          : `<span class="pos-icon">📍</span>${arrow}`;
        btn.classList.toggle('selected', isSelected);
      });
      const btn = document.getElementById('tut-confirm-btn');
      btn.disabled = false;
      btn.classList.add('btn-pulse');
      document.getElementById('tut-hint').textContent = `Posición ${pos + 1} seleccionada — pulsa para confirmar`;
    }

    function tutConfirm() {
      const song = TUT_SONGS[tutRound];
      // En la ronda 3 forzamos un fallo didáctico eligiendo mal a propósito
      const forceWrong = tutRound === 2;

      // Calcular si la posición elegida es correcta según el año real
      const correctPos = tutTimeline.filter(s => s.year < song.year).length;
      const isCorrect  = forceWrong ? false : (tutSelectedPos === correctPos);

      document.getElementById('tut-year-peek').textContent = song.year;

      const resIcon    = document.getElementById('tut-res-icon');
      const resMsg     = document.getElementById('tut-res-msg');
      const resPts     = document.getElementById('tut-res-pts');
      const resExplain = document.getElementById('tut-res-explain');

      if (isCorrect) {
        tutStreak++;
        const pts = 80 + tutStreak * 20;
        resIcon.textContent = '✅';
        resMsg.textContent  = '¡Acierto!';
        resPts.textContent  = `+${pts} pts`;
        resExplain.textContent = `${song.title} es de ${song.year} — la colocaste en el sitio correcto. Racha: ${tutStreak} 🔥`;
        // Insertar en la timeline ordenada
        tutTimeline.splice(correctPos, 0, song);
      } else {
        tutStreak = 0;
        resIcon.textContent = '❌';
        resMsg.textContent  = '¡Fallo!';
        resPts.textContent  = '+0 pts';
        resExplain.textContent = `${song.title} es de ${song.year} — no encajaba ahí. La canción no se añade a tu línea del tiempo y pierdes la racha.`;
      }

      document.getElementById('tut-res-next').textContent = tutRound < TUT_SONGS.length - 1 ? 'Siguiente ronda →' : '🎉 Terminar tutorial';
      document.getElementById('tut-result').classList.remove('d-none');
      document.getElementById('tut-result').classList.add('d-flex');
    }

    function tutNext() {
      tutRound++;
      if (tutRound >= TUT_SONGS.length) {
        closeTutorial();
        return;
      }
      tutRenderRound();
    }

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