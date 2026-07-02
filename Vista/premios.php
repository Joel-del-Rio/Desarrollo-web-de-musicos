<?php
/**
 * premios.php — Página pública de premios y ranking global
 *
 * Tres pestañas:
 *   - Premios: catálogo de premios canjeables con puntos
 *   - Clasificación: ranking global top 50
 *   - Mis puntos: consulta de puntos por email
 *
 * También incluye un panel de administración oculto (acceso con contraseña)
 * para gestionar el catálogo de premios desde esta misma página.
 */
require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Premios</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=5">
  <style>
    .prize-card {
      background: var(--card);
      border: 1.5px solid rgba(255,255,255,.08);
      border-radius: 14px;
      padding: 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: border-color .2s;
    }
    .prize-card:hover { border-color: var(--accent); }
    .prize-pts {
      background: rgba(233,69,96,.15);
      color: var(--accent);
      border-radius: 8px;
      padding: .35rem .7rem;
      font-weight: 900;
      font-size: .85rem;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .tab-btn {
      background: none; border: none; color: var(--muted);
      padding: .5rem 1.1rem; border-radius: 50px;
      font-weight: 600; cursor: pointer;
      transition: background .15s, color .15s;
      font-size: .9rem;
    }
    .tab-btn.active { background: var(--accent); color: #fff; }

    /* Admin panel */
    #admin-panel { border-top: 1.5px solid rgba(255,255,255,.07); margin-top: 2rem; padding-top: 1.5rem; }
    .admin-table th, .admin-table td { vertical-align: middle; }
  </style>
</head>
<body>

<div class="container py-4" style="max-width:580px">

  <div class="text-center mb-4">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="max-width:160px">
    <h4 class="fw-black mt-3 mb-0">🏆 Premios</h4>
  </div>

  <!-- Tabs públicas -->
  <div class="d-flex gap-1 justify-content-center mb-4 p-1 rounded-pill" style="background:rgba(255,255,255,.05)">
    <button class="tab-btn active" id="tab-prizes" onclick="showTab('prizes')">Premios</button>
    <button class="tab-btn"        id="tab-rank"   onclick="showTab('rank')">Clasificación</button>
    <button class="tab-btn"        id="tab-me"     onclick="showTab('me')">Mis puntos</button>
  </div>

  <!-- TAB: Premios -->
  <div id="panel-prizes">
    <div id="prizes-list">
      <div class="text-center py-5"><div class="game-spinner mx-auto"></div></div>
    </div>
    <!-- Cómo conseguir puntos -->
    <div class="card p-4 mt-4">
      <div class="fw-black mb-3" style="font-size:1.1rem">🎯 ¿Cómo conseguir puntos?</div>
      <div class="d-flex flex-column gap-3">

        <div class="d-flex gap-3 align-items-start">
          <div style="background:var(--accent);color:#fff;border-radius:50%;width:32px;height:32px;
                      display:flex;align-items:center;justify-content:center;font-weight:900;flex-shrink:0">1</div>
          <div>
            <div class="fw-semibold">Juega con PIN individual</div>
            <div class="small mt-1" style="color:var(--muted)">
              Pide a tu organizador que te asigne un PIN personal. Solo las partidas con PIN individual suman puntos al ranking global.
            </div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <div style="background:var(--accent);color:#fff;border-radius:50%;width:32px;height:32px;
                      display:flex;align-items:center;justify-content:center;font-weight:900;flex-shrink:0">2</div>
          <div>
            <div class="fw-semibold">Introduce tu email al unirte</div>
            <div class="small mt-1" style="color:var(--muted)">
              Al entrar a la partida con tu PIN, pon tu dirección de email. Es la única forma de que tus puntos queden registrados a tu nombre.
            </div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <div style="background:var(--accent);color:#fff;border-radius:50%;width:32px;height:32px;
                      display:flex;align-items:center;justify-content:center;font-weight:900;flex-shrink:0">3</div>
          <div>
            <div class="fw-semibold">Acierta canciones y sube en el ranking</div>
            <div class="small mt-1" style="color:var(--muted)">
              Cada canción que coloques correctamente en tu línea del tiempo te da puntos. Cuanto más rápido respondas, más puntos consigues.
            </div>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-start">
          <div style="background:var(--accent);color:#fff;border-radius:50%;width:32px;height:32px;
                      display:flex;align-items:center;justify-content:center;font-weight:900;flex-shrink:0">4</div>
          <div>
            <div class="fw-semibold">Canjea tu premio</div>
            <div class="small mt-1" style="color:var(--muted)">
              Cuando acumules suficientes puntos, contacta con el organizador para reclamar tu premio. Puedes consultar tu saldo en la pestaña <strong>Mis puntos</strong>.
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- TAB: Clasificación -->
  <div id="panel-rank" class="d-none">
    <div id="rank-list">
      <div class="text-center py-5"><div class="game-spinner mx-auto"></div></div>
    </div>
  </div>

  <!-- TAB: Mis puntos -->
  <div id="panel-me" class="d-none">
    <div class="card p-4">
      <div class="fw-semibold mb-3">Consulta tus puntos acumulados</div>
      <input id="my-email" type="email" class="form-control mb-3" placeholder="tu@email.com" autocomplete="email">
      <button class="btn btn-game w-100 rounded-pill fw-bold" onclick="lookupMyScore()">Ver mis puntos →</button>
      <div id="my-result" class="mt-3 d-none"></div>
    </div>
  </div>

  <!-- ── ADMIN PANEL (oculto hasta login) ── -->
  <div id="admin-panel" class="d-none">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <span class="fw-bold" style="color:var(--accent)">🔧 Panel de administración</span>
      <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="adminLogout()">Cerrar sesión</button>
    </div>

    <!-- Formulario añadir / editar -->
    <div class="card p-4 mb-4">
      <div class="fw-semibold mb-3" id="admin-form-title">Añadir premio</div>
      <input type="hidden" id="edit-id" value="0">
      <div class="row g-3 mb-3">
        <div class="col-12">
          <input type="text" id="f-name" class="form-control" placeholder="Nombre del premio *" maxlength="200">
        </div>
        <div class="col-12">
          <textarea id="f-desc" class="form-control" rows="2" placeholder="Descripción (opcional)" maxlength="500"></textarea>
        </div>
        <div class="col-6">
          <label class="form-label small text-secondary">Puntos necesarios</label>
          <input type="number" id="f-cost" class="form-control" value="1000" min="1">
        </div>
        <div class="col-6">
          <label class="form-label small text-secondary">Stock (-1 = ilimitado)</label>
          <input type="number" id="f-stock" class="form-control" value="-1" min="-1">
        </div>
        <div class="col-12">
          <label class="form-label small text-secondary">Imagen <span style="opacity:.6">(opcional, máx. 2 MB — JPG, PNG, GIF, WebP)</span></label>
          <input type="file" id="f-image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp"
                 onchange="previewImage(this)">
          <div id="f-image-preview" class="mt-2 d-none">
            <img id="f-image-thumb" src="" alt="Vista previa"
                 style="max-height:100px;max-width:200px;border-radius:8px;object-fit:cover;border:1.5px solid rgba(255,255,255,.12)">
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill ms-2 px-2 py-0"
                    onclick="clearImage()" style="font-size:.75rem">✕ Quitar</button>
          </div>
          <div id="f-image-current" class="mt-2 d-none small" style="color:var(--muted)">
            Imagen actual: <img id="f-image-cur-thumb" src="" alt=""
              style="height:40px;border-radius:6px;object-fit:cover;vertical-align:middle;margin-left:6px">
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-game rounded-pill px-4 fw-bold" onclick="savePrize()">Guardar</button>
        <button class="btn btn-outline-secondary rounded-pill px-4 d-none" id="btn-cancel" onclick="cancelEdit()">Cancelar</button>
      </div>
      <div id="form-error" class="alert alert-danger mt-3 py-2 small d-none"></div>
    </div>

    <!-- Lista de todos los premios -->
    <div id="admin-prizes-list">
      <div class="text-center py-3"><div class="game-spinner mx-auto"></div></div>
    </div>
  </div>

  <!-- Botón admin discreto -->
  <div class="text-center mt-4 pb-2" id="admin-btn-wrap">
    <button class="btn btn-link btn-sm text-secondary" style="font-size:.75rem;opacity:.4" onclick="showLoginModal()">
      🔒 Admin
    </button>
  </div>

</div><!-- /container -->

<div class="text-center pb-4">
  <a href="<?= BASE_URL ?>/Vista/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">‹ Inicio</a>
</div>

<!-- ── MODAL LOGIN ── -->
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
    <div class="modal-content" style="background:var(--card);border:1.5px solid rgba(255,255,255,.1)">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-4 text-center">Acceso de administrador</h6>
        <div class="mb-3">
          <label class="form-label small text-secondary">Correo</label>
          <input type="email" id="login-email" class="form-control" placeholder="admin@ejemplo.com" autocomplete="email">
        </div>
        <div class="mb-3">
          <label class="form-label small text-secondary">Contraseña</label>
          <input type="password" id="login-pass" class="form-control" autocomplete="current-password"
                 onkeydown="if(event.key==='Enter') doLogin()">
        </div>
        <div id="login-error" class="alert alert-danger py-2 small d-none mb-3"></div>
        <div class="d-flex gap-2">
          <button class="btn btn-game w-100 rounded-pill fw-bold" onclick="doLogin()">Entrar</button>
          <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';

/* ── Login modal ── */
let loginModal;
window.addEventListener('DOMContentLoaded', () => {
  loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
  if (sessionStorage.getItem('premios_admin') === '1') showAdminPanel();
  loadPrizes();
});

function showLoginModal() {
  document.getElementById('login-email').value = '';
  document.getElementById('login-pass').value  = '';
  document.getElementById('login-error').classList.add('d-none');
  loginModal.show();
  setTimeout(() => document.getElementById('login-email').focus(), 400);
}

async function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-pass').value;
  const errEl = document.getElementById('login-error');
  const btn   = document.querySelector('#loginModal .btn-game');
  errEl.classList.add('d-none');
  btn.disabled = true; btn.textContent = '…';

  try {
    const r = await fetch(`${API}?action=admin_login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ email, password: pass }),
    });
    const d = await r.json();
    if (d.success) {
      sessionStorage.setItem('premios_admin', '1');
      loginModal.hide();
      showAdminPanel();
    } else {
      errEl.textContent = d.error || 'Credenciales incorrectas';
      errEl.classList.remove('d-none');
      document.getElementById('login-pass').value = '';
      document.getElementById('login-pass').focus();
    }
  } catch {
    errEl.textContent = 'Error de conexión';
    errEl.classList.remove('d-none');
  }
  btn.disabled = false; btn.textContent = 'Entrar';
}

function showAdminPanel() {
  document.getElementById('admin-panel').classList.remove('d-none');
  document.getElementById('admin-btn-wrap').classList.add('d-none');
  loadAdminPrizes();
}

function adminLogout() {
  sessionStorage.removeItem('premios_admin');
  document.getElementById('admin-panel').classList.add('d-none');
  document.getElementById('admin-btn-wrap').classList.remove('d-none');
}

/* ── Tabs ── */
let prizesLoaded = false, rankLoaded = false;
function showTab(tab) {
  ['prizes','rank','me'].forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('d-none', t !== tab);
    document.getElementById('tab-'   + t).classList.toggle('active', t === tab);
  });
  if (tab === 'prizes' && !prizesLoaded) loadPrizes();
  if (tab === 'rank'   && !rankLoaded)   loadRank();
}

/* ── Premios públicos ── */
async function loadPrizes() {
  prizesLoaded = true;
  try {
    const r    = await fetch(`${API}?action=get_prizes_catalog&_t=${Date.now()}`, { cache: 'no-store' });
    const data = await r.json();
    renderPrizes(data);
  } catch { document.getElementById('prizes-list').innerHTML = '<p class="text-center text-secondary">Error al cargar</p>'; }
}

function renderPrizes(prizes) {
  const el = document.getElementById('prizes-list');
  if (!prizes.length) {
    el.innerHTML = '<div class="text-center py-4 text-secondary small">No hay premios configurados todavía.<br>¡Vuelve pronto!</div>';
    return;
  }
  const base = '<?= BASE_URL ?>/assets/images/premios/';
  el.innerHTML = prizes.map(p => `
    <div class="prize-card mb-3">
      ${p.image
        ? `<img src="${base}${esc(p.image)}" alt="${esc(p.name)}"
               style="width:56px;height:56px;object-fit:cover;border-radius:10px;flex-shrink:0">`
        : `<div style="font-size:2rem;flex-shrink:0">🎁</div>`}
      <div style="flex:1;min-width:0">
        <div class="fw-bold">${esc(p.name)}</div>
        ${p.description ? `<div class="small" style="color:var(--muted)">${esc(p.description)}</div>` : ''}
        ${p.stock > 0 ? `<div class="small mt-1" style="color:var(--muted)">Quedan: ${p.stock}</div>` : ''}
      </div>
      <div class="prize-pts">${Number(p.points_cost).toLocaleString()} pts</div>
    </div>`).join('');
}

/* ── Clasificación ── */
async function loadRank() {
  rankLoaded = true;
  try {
    const r    = await fetch(`${API}?action=get_global_leaderboard&_t=${Date.now()}`, { cache: 'no-store' });
    const data = await r.json();
    renderRank(data);
  } catch { document.getElementById('rank-list').innerHTML = '<p class="text-center text-secondary">Error al cargar</p>'; }
}

function renderRank(players) {
  const el = document.getElementById('rank-list');
  if (!players.length) {
    el.innerHTML = '<div class="text-center py-4 text-secondary small">Nadie ha acumulado puntos todavía.<br>¡Sé el primero!</div>';
    return;
  }
  const medals = ['🥇','🥈','🥉'];
  el.innerHTML = players.map((p, i) => `
    <div class="lb-row mb-1">
      <span class="lb-rank">${medals[i] ?? (i+1) + '.'}</span>
      <span class="avatar-circle" style="background:var(--accent)">${esc(p.name[0].toUpperCase())}</span>
      <span class="lb-name">${esc(p.name)}</span>
      <span class="lb-score">${Number(p.total_points).toLocaleString()} pts</span>
    </div>`).join('');
}

/* ── Mis puntos ── */
async function lookupMyScore() {
  const email = document.getElementById('my-email').value.trim();
  const res   = document.getElementById('my-result');
  res.classList.add('d-none');
  if (!email) return;
  try {
    const r    = await fetch(`${API}?action=get_my_score&email=${encodeURIComponent(email)}&_t=${Date.now()}`, { cache: 'no-store' });
    const data = await r.json();
    if (data.error) { res.innerHTML = `<div class="alert alert-danger py-2 small">${esc(data.error)}</div>`; res.classList.remove('d-none'); return; }
    if (!data.total_points) {
      res.innerHTML = `<div class="text-center py-2 small text-secondary">Todavía no tienes puntos con este email.<br>Introduce tu email al unirte a una partida.</div>`;
    } else {
      res.innerHTML = `
        <div class="text-center">
          <div class="fw-black" style="font-size:3rem;color:var(--accent)">${Number(data.total_points).toLocaleString()}</div>
          <div class="text-secondary small">puntos acumulados</div>
          ${data.rank ? `<div class="mt-2 small fw-semibold">Posición global: <span style="color:var(--accent)">#${data.rank}</span></div>` : ''}
          <div class="mt-3 small text-secondary">Para canjear un premio contacta con el organizador.</div>
        </div>`;
    }
    res.classList.remove('d-none');
  } catch { res.innerHTML = '<div class="alert alert-danger py-2 small">Error de conexión</div>'; res.classList.remove('d-none'); }
}
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('my-email')?.addEventListener('keydown', e => { if (e.key === 'Enter') lookupMyScore(); });
});

/* ── Admin: gestión de premios ── */
let allPrizes = [];

async function loadAdminPrizes() {
  const r = await fetch(`${API}?action=get_prizes_all&_t=${Date.now()}`, { cache: 'no-store' });
  allPrizes = await r.json();
  renderAdminPrizes();
}

function renderAdminPrizes() {
  const el = document.getElementById('admin-prizes-list');
  if (!allPrizes.length) {
    el.innerHTML = '<div class="text-center py-3 text-secondary small">No hay premios todavía</div>';
    return;
  }
  el.innerHTML = `
    <div class="card p-0 overflow-hidden">
    <table class="table table-dark table-hover mb-0 admin-table" style="--bs-table-bg:var(--card);--bs-table-hover-bg:rgba(255,255,255,.04)">
      <thead><tr>
        <th class="small">Premio</th>
        <th class="small" style="width:90px">Puntos</th>
        <th class="small" style="width:70px">Stock</th>
        <th class="small" style="width:110px">Acciones</th>
      </tr></thead>
      <tbody>
        ${allPrizes.map(p => `
        <tr style="${p.active ? '' : 'opacity:.4'}">
          <td>
            <div class="d-flex align-items-center gap-2">
              ${p.image
                ? `<img src="<?= BASE_URL ?>/assets/images/premios/${esc(p.image)}" alt=""
                       style="width:36px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0">`
                : `<span style="font-size:1.5rem">🎁</span>`}
              <div>
                <div class="fw-semibold small">${esc(p.name)}</div>
                ${p.description ? `<div style="font-size:.72rem;color:var(--muted)">${esc(p.description)}</div>` : ''}
              </div>
            </div>
          </td>
          <td class="small fw-bold" style="color:var(--accent)">${Number(p.points_cost).toLocaleString()}</td>
          <td class="small">${p.stock < 0 ? '∞' : p.stock}</td>
          <td>
            <div class="d-flex gap-1">
              <button onclick="editPrize(${p.id})" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:.72rem" title="Editar">✏️</button>
              <button onclick="togglePrize(${p.id})" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:.72rem" title="${p.active ? 'Ocultar' : 'Mostrar'}">${p.active ? '🙈' : '👁'}</button>
              <button onclick="deletePrize(${p.id},'${esc(p.name)}')" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" style="font-size:.72rem" title="Eliminar">🗑️</button>
            </div>
          </td>
        </tr>`).join('')}
      </tbody>
    </table></div>`;
}

async function savePrize() {
  const id    = parseInt(document.getElementById('edit-id').value, 10) || 0;
  const name  = document.getElementById('f-name').value.trim();
  const desc  = document.getElementById('f-desc').value.trim();
  const cost  = parseInt(document.getElementById('f-cost').value, 10) || 1000;
  const stock = parseInt(document.getElementById('f-stock').value, 10);
  const errEl = document.getElementById('form-error');
  errEl.classList.add('d-none');
  if (!name) { errEl.textContent = 'El nombre es obligatorio'; errEl.classList.remove('d-none'); return; }

  const fd = new FormData();
  fd.append('id',           id);
  fd.append('name',         name);
  fd.append('description',  desc);
  fd.append('points_cost',  cost);
  fd.append('stock',        stock);
  const imgFile = document.getElementById('f-image').files[0];
  if (imgFile) fd.append('image', imgFile);

  const r = await fetch(`${API}?action=save_prize`, { method: 'POST', body: fd });
  const d = await r.json();
  if (d.error) { errEl.textContent = d.error; errEl.classList.remove('d-none'); return; }
  cancelEdit();
  loadAdminPrizes();
  prizesLoaded = false; loadPrizes();
}

function editPrize(id) {
  const p = allPrizes.find(x => x.id == id);
  if (!p) return;
  document.getElementById('edit-id').value         = p.id;
  document.getElementById('f-name').value          = p.name;
  document.getElementById('f-desc').value          = p.description || '';
  document.getElementById('f-cost').value          = p.points_cost;
  document.getElementById('f-stock').value         = p.stock;
  clearImage();
  // Mostrar imagen actual si existe
  const curWrap = document.getElementById('f-image-current');
  const curThumb = document.getElementById('f-image-cur-thumb');
  if (p.image) {
    curThumb.src = `<?= BASE_URL ?>/assets/images/premios/${p.image}`;
    curWrap.classList.remove('d-none');
  } else {
    curWrap.classList.add('d-none');
  }
  document.getElementById('admin-form-title').textContent = 'Editar premio';
  document.getElementById('btn-cancel').classList.remove('d-none');
  document.getElementById('f-name').focus();
  document.getElementById('admin-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelEdit() {
  document.getElementById('edit-id').value = '0';
  document.getElementById('f-name').value  = '';
  document.getElementById('f-desc').value  = '';
  document.getElementById('f-cost').value  = '1000';
  document.getElementById('f-stock').value = '-1';
  clearImage();
  document.getElementById('f-image-current').classList.add('d-none');
  document.getElementById('admin-form-title').textContent = 'Añadir premio';
  document.getElementById('btn-cancel').classList.add('d-none');
  document.getElementById('form-error').classList.add('d-none');
}

function previewImage(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('f-image-thumb').src = e.target.result;
    document.getElementById('f-image-preview').classList.remove('d-none');
  };
  reader.readAsDataURL(file);
}

function clearImage() {
  document.getElementById('f-image').value = '';
  document.getElementById('f-image-preview').classList.add('d-none');
  document.getElementById('f-image-thumb').src = '';
}

async function togglePrize(id) {
  await fetch(`${API}?action=toggle_prize`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id }),
  });
  loadAdminPrizes();
  loadPrizes();
}

async function deletePrize(id, name) {
  if (!confirm(`¿Eliminar "${name}"?`)) return;
  await fetch(`${API}?action=delete_prize`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id }),
  });
  loadAdminPrizes();
  loadPrizes();
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>
</body>
</html>
