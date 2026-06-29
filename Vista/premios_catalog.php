<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hitstoric — Gestionar premios</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=2">
</head>
<body>

<div class="container py-4" style="max-width:760px">

  <div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= BASE_URL ?>/assets/images/Logo.png" alt="Hitstoric" style="height:48px">
    <div>
      <h4 class="fw-black mb-0">Gestionar premios</h4>
      <div class="small" style="color:var(--muted)">Catálogo de premios canjeables por puntos</div>
    </div>
    <a href="<?= BASE_URL ?>/Vista/admin.php" class="btn btn-outline-secondary btn-sm rounded-pill ms-auto">‹ Volver al panel</a>
  </div>

  <!-- Formulario añadir / editar -->
  <div class="card p-4 mb-4">
    <div class="fw-semibold mb-3" id="form-title">Añadir premio</div>
    <input type="hidden" id="edit-id" value="0">
    <div class="row g-3 mb-3">
      <div class="col-12">
        <input type="text" id="f-name" class="form-control" placeholder="Nombre del premio *" maxlength="200">
      </div>
      <div class="col-12">
        <input type="text" id="f-desc" class="form-control" placeholder="Descripción (opcional)" maxlength="500">
      </div>
      <div class="col-6">
        <label class="form-label small text-secondary">Puntos necesarios</label>
        <input type="number" id="f-cost" class="form-control" value="1000" min="1">
      </div>
      <div class="col-6">
        <label class="form-label small text-secondary">Stock (-1 = ilimitado)</label>
        <input type="number" id="f-stock" class="form-control" value="-1" min="-1">
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-game rounded-pill px-4 fw-bold" onclick="savePrize()">Guardar</button>
      <button class="btn btn-outline-secondary rounded-pill px-4 d-none" id="btn-cancel" onclick="cancelEdit()">Cancelar</button>
    </div>
    <div id="form-error" class="alert alert-danger mt-3 py-2 small d-none"></div>
  </div>

  <!-- Lista de premios -->
  <div id="prizes-list">
    <div class="text-center py-5"><div class="game-spinner mx-auto"></div></div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_URL ?>/Controlador/api.php';
let allPrizes = [];

async function loadPrizes() {
  const r = await fetch(`${API}?action=get_prizes_all&_t=${Date.now()}`, { cache: 'no-store' });
  allPrizes = await r.json();
  renderPrizes();
}

function renderPrizes() {
  const el = document.getElementById('prizes-list');
  if (!allPrizes.length) { el.innerHTML = '<div class="text-center py-4 text-secondary small">No hay premios todavía</div>'; return; }
  el.innerHTML = `
    <div class="card p-0 overflow-hidden">
    <table class="table table-dark table-hover mb-0" style="--bs-table-bg:var(--card);--bs-table-hover-bg:rgba(255,255,255,.04)">
      <thead><tr>
        <th class="small">Premio</th>
        <th class="small" style="width:110px">Puntos</th>
        <th class="small" style="width:80px">Stock</th>
        <th class="small" style="width:140px">Acciones</th>
      </tr></thead>
      <tbody>
        ${allPrizes.map(p => `
        <tr style="${p.active ? '' : 'opacity:.45'}">
          <td>
            <div class="fw-semibold small">${esc(p.name)}</div>
            ${p.description ? `<div style="font-size:.75rem;color:var(--muted)">${esc(p.description)}</div>` : ''}
          </td>
          <td class="small fw-bold" style="color:var(--accent)">${Number(p.points_cost).toLocaleString()}</td>
          <td class="small">${p.stock < 0 ? '∞' : p.stock}</td>
          <td>
            <div class="d-flex gap-1">
              <button onclick="editPrize(${p.id})" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:.75rem">✏️</button>
              <button onclick="togglePrize(${p.id})" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:.75rem">${p.active ? '🙈' : '👁'}</button>
              <button onclick="deletePrize(${p.id},'${esc(p.name)}')" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" style="font-size:.75rem">🗑️</button>
            </div>
          </td>
        </tr>`).join('')}
      </tbody>
    </table></div>`;
}

async function savePrize() {
  const id   = parseInt(document.getElementById('edit-id').value, 10) || 0;
  const name = document.getElementById('f-name').value.trim();
  const desc = document.getElementById('f-desc').value.trim();
  const cost = parseInt(document.getElementById('f-cost').value, 10) || 1000;
  const stock = parseInt(document.getElementById('f-stock').value, 10);
  const errEl = document.getElementById('form-error');
  errEl.classList.add('d-none');
  if (!name) { errEl.textContent = 'El nombre es obligatorio'; errEl.classList.remove('d-none'); return; }
  const r = await fetch(`${API}?action=save_prize`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id, name, description: desc, points_cost: cost, stock }),
  });
  const d = await r.json();
  if (d.error) { errEl.textContent = d.error; errEl.classList.remove('d-none'); return; }
  cancelEdit();
  loadPrizes();
}

function editPrize(id) {
  const p = allPrizes.find(x => x.id == id);
  if (!p) return;
  document.getElementById('edit-id').value    = p.id;
  document.getElementById('f-name').value     = p.name;
  document.getElementById('f-desc').value     = p.description || '';
  document.getElementById('f-cost').value     = p.points_cost;
  document.getElementById('f-stock').value    = p.stock;
  document.getElementById('form-title').textContent = 'Editar premio';
  document.getElementById('btn-cancel').classList.remove('d-none');
  document.getElementById('f-name').focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
  document.getElementById('edit-id').value = '0';
  document.getElementById('f-name').value  = '';
  document.getElementById('f-desc').value  = '';
  document.getElementById('f-cost').value  = '1000';
  document.getElementById('f-stock').value = '-1';
  document.getElementById('form-title').textContent = 'Añadir premio';
  document.getElementById('btn-cancel').classList.add('d-none');
  document.getElementById('form-error').classList.add('d-none');
}

async function togglePrize(id) {
  await fetch(`${API}?action=toggle_prize`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id }),
  });
  loadPrizes();
}

async function deletePrize(id, name) {
  if (!confirm(`¿Eliminar "${name}"?`)) return;
  await fetch(`${API}?action=delete_prize`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id }),
  });
  loadPrizes();
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

loadPrizes();
</script>
</body>
</html>
