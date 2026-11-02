/* ═══════════════════════════════════════════════════════════════
 * fx.js — Efectos de "fiesta": confeti, sacudidas, vibración,
 * cuenta atrás nerviosa y notas musicales flotando.
 *
 * Solo observa el DOM (qué pantalla está activa, qué icono de resultado
 * sale, qué número marca la cuenta atrás): no toca la lógica de
 * player.js ni admin.js, así que se puede quitar sin romper nada.
 * ═══════════════════════════════════════════════════════════════ */
(() => {
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Chrome bloquea (y avisa en consola) la vibración hasta que la persona toca la página
  const vibrate = pattern => {
    if (navigator.userActivation && !navigator.userActivation.hasBeenActive) return;
    try { navigator.vibrate?.(pattern); } catch {}
  };

  /* ── Notas musicales que suben despacio detrás de todo ── */
  function spawnNotes() {
    if (reduceMotion || document.getElementById('fx-notes')) return;
    const layer = document.createElement('div');
    layer.id = 'fx-notes';
    layer.setAttribute('aria-hidden', 'true');
    const glyphs = ['♪', '♫', '♩', '♬'];
    for (let i = 0; i < 10; i++) {
      const note = document.createElement('span');
      note.textContent = glyphs[i % glyphs.length];
      note.style.left = (4 + Math.random() * 92) + '%';
      note.style.fontSize = (1 + Math.random() * 1.6) + 'rem';
      note.style.animationDuration = (14 + Math.random() * 14) + 's';
      // Retardo negativo: cada nota arranca ya a mitad de su recorrido
      note.style.animationDelay = (-Math.random() * 28) + 's';
      layer.appendChild(note);
    }
    document.body.appendChild(layer);
  }

  /* ── Confeti dibujado en un canvas temporal que se borra solo ── */
  function confetti(amount = 140, duration = 2600) {
    if (reduceMotion) return;
    const canvas = document.createElement('canvas');
    canvas.className = 'fx-confetti';
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const W = innerWidth, H = innerHeight;
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    document.body.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const colors = ['#e94560', '#ff5f8f', '#ff8a3d', '#ffd43b', '#7c5cff', '#2de2e6', '#2ecc71'];
    const pieces = Array.from({ length: amount }, () => ({
      x: W / 2 + (Math.random() - .5) * W * .3,
      y: H * .35,
      vx: (Math.random() - .5) * 14,
      vy: -6 - Math.random() * 10,
      w: 6 + Math.random() * 6,
      h: 8 + Math.random() * 8,
      rot: Math.random() * Math.PI,
      vr: (Math.random() - .5) * .3,
      color: colors[Math.floor(Math.random() * colors.length)],
    }));

    const start = performance.now();
    (function frame(now) {
      const t = now - start;
      ctx.clearRect(0, 0, W, H);
      // Se desvanece durante el último 40 % de la animación
      ctx.globalAlpha = Math.max(0, 1 - Math.max(0, t - duration * .6) / (duration * .4));
      for (const p of pieces) {
        p.vy += .35;
        p.vx *= .99;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        // cos() hace que el papelito "gire" sobre sí mismo
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.cos(p.rot * 2));
        ctx.restore();
      }
      if (t < duration) requestAnimationFrame(frame);
      else canvas.remove();
    })(start);
  }

  function shake(el) {
    if (!el || reduceMotion) return;
    el.classList.remove('fx-shake');
    void el.offsetWidth; // reinicia la animación si se repite seguido
    el.classList.add('fx-shake');
  }

  /* ── El año de la canción gira como una tragaperras hasta caer en el bueno ──
     Vale para "1985" (jugador) y "📅 1985" (dinamizador): solo cambia los 4 dígitos */
  function rollYear(el, duration = 1100) {
    if (!el || reduceMotion) return;
    const final = el.textContent;
    const match = final.match(/^(.*?)(\d{4})(.*)$/);
    if (!match) return;
    const [, before, , after] = match;
    let done = false;
    const land = () => {
      if (done) return;
      done = true;
      el.textContent = final;
      el.classList.remove('fx-landed');
      void el.offsetWidth;
      el.classList.add('fx-landed');
    };
    // Red de seguridad: con la pestaña en segundo plano el navegador pausa
    // requestAnimationFrame, y el año no puede quedarse en uno inventado
    setTimeout(land, duration + 150);

    const start = performance.now();
    let lastTick = 0;
    (function frame(now) {
      if (done) return;
      const t = now - start;
      if (t >= duration) return land();
      // Cada vez cambia más despacio, como una ruleta que se frena
      if (now - lastTick > 45 + t / 8) {
        lastTick = now;
        el.textContent = before + (1960 + Math.floor(Math.random() * 66)) + after;
      }
      requestAnimationFrame(frame);
    })(start);
  }

  /* ── Los puntos suben contando ("+0 pts" → "+120 pts") ── */
  function countUp(el, duration = 900) {
    if (!el || reduceMotion) return;
    const final = el.textContent;
    const match = final.match(/^(\D*)(\d+)(.*)$/);
    if (!match || Number(match[2]) === 0) return;
    const [, before, digits, after] = match;
    const target = Number(digits);
    let done = false;
    const finish = () => { done = true; el.textContent = final; };
    setTimeout(() => { if (!done) finish(); }, duration + 150); // igual que en rollYear

    const start = performance.now();
    (function frame(now) {
      if (done) return;
      const k = Math.min(1, (now - start) / duration);
      if (k >= 1) return finish();
      const eased = 1 - Math.pow(1 - k, 3);
      el.textContent = before + Math.round(target * eased) + after;
      requestAnimationFrame(frame);
    })(start);
  }

  /* ── Reaccionar cuando cambia la pantalla activa ── */
  function onScreenChange(id) {
    // El JS del juego rellena la pantalla justo al mostrarla: esperar un instante
    setTimeout(() => {
      if (id === 'screen-results') {
        rollYear(document.getElementById('r-year'));
        countUp(document.getElementById('res-pts'));
      }
      if (id === 'screen-finished') countUp(document.getElementById('f-score'), 1400);

      if (id === 'screen-results') {
        const icon = document.getElementById('res-icon')?.textContent || '';
        if (icon.includes('✅')) {
          confetti(90, 2200);
          vibrate([30, 40, 30]);
        } else if (icon.includes('❌')) {
          shake(document.querySelector('#screen-results > .container') || document.getElementById('screen-results'));
          vibrate(180);
        }
      } else if (id === 'screen-finished') {
        // Jugador: "Posición final: 2 / 5". Dinamizador: no hay posición → fiesta completa
        const match = (document.getElementById('f-rank')?.textContent || '').match(/(\d+)\s*\/\s*\d+/);
        const rank = match ? Number(match[1]) : null;
        if (rank === null || rank <= 3) {
          confetti(rank === null || rank === 1 ? 220 : 120, 3400);
          vibrate([40, 60, 40, 60, 120]);
        }
      }
    }, 120);
  }

  let currentScreen = null;
  new MutationObserver(() => {
    const id = document.querySelector('.screen.active')?.id || null;
    if (id !== currentScreen) {
      currentScreen = id;
      if (id) onScreenChange(id);
    }
  }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });

  /* ── Cuenta atrás: late en rojo los últimos 5 segundos ── */
  function watchCountdown(el) {
    if (!el) return;
    const check = () => {
      const n = parseInt(el.textContent, 10);
      const urgent = Number.isFinite(n) && n > 0 && n <= 5;
      el.classList.toggle('fx-urgent', urgent);
      if (urgent && n <= 3 && el.dataset.fxLast !== String(n)) {
        el.dataset.fxLast = String(n);
        vibrate(25);
      }
    };
    new MutationObserver(check).observe(el, { childList: true, characterData: true, subtree: true });
    check();
  }

  spawnNotes();
  watchCountdown(document.getElementById('q-secs'));   // jugador
  watchCountdown(document.getElementById('q-timer'));  // dinamizador
})();
