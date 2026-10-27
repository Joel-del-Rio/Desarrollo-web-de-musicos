<nav class="container py-3 d-flex flex-wrap gap-2 justify-content-between" style="max-width:960px" aria-label="Gestión de partidas">
  <button class="btn btn-outline-light rounded-pill" onclick="openSavedGames()">Mis partidas</button>
  <a class="btn btn-outline-light rounded-pill" href="#event-prizes">Premios de la partida</a>
  <button class="btn btn-outline-secondary rounded-pill" onclick="showHostLink()">Continuar en otro dispositivo</button>
</nav>
<section id="screen-library" class="screen">
  <div class="container py-3" style="max-width:900px">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-3">
      <div><h1 class="h3">Mis partidas</h1><p class="text-secondary mb-0">Prepáralas ahora y empieza cuando estés listo.</p></div>
      <button class="btn btn-game rounded-pill" onclick="newGame()">+ Preparar partida</button>
    </div>
    <p class="small text-secondary">Se guardan automáticamente en el servidor. Usa tu enlace privado para abrirlas desde el móvil o el ordenador.</p>
    <div id="saved-games" class="d-grid gap-3" aria-live="polite"></div>
  </div>
</section>
<dialog id="host-link-dialog" class="planner-dialog">
  <h2 class="h5">Tu acceso privado</h2>
  <p>Abre este enlace en tu otro dispositivo para gestionar las mismas partidas. Guárdalo: permite administrarlas. No lo entregues a los jugadores.</p>
  <label for="host-link" class="form-label">Enlace privado de gestión</label>
  <textarea id="host-link" class="form-control mb-3" rows="4" readonly></textarea>
  <div class="d-flex flex-wrap gap-2"><button class="btn btn-game" onclick="copyText(document.getElementById('host-link').value)">Copiar enlace</button><button class="btn btn-outline-secondary" onclick="document.getElementById('host-link-dialog').close()">Cerrar</button></div>
</dialog>
<dialog id="invitation-dialog" class="planner-dialog">
  <form id="invitation-edit-form">
    <h2 class="h5">Editar jugador invitado</h2>
    <input id="edit-invitation-id" type="hidden"><input id="edit-player-id" type="hidden">
    <label for="edit-invitation-name" class="form-label">Nombre</label>
    <input id="edit-invitation-name" class="form-control mb-3" maxlength="30" autocomplete="off">
    <label for="edit-invitation-email" class="form-label">Correo (opcional)</label>
    <input id="edit-invitation-email" class="form-control mb-3" type="email" maxlength="255" autocomplete="off">
    <p class="small text-secondary">Guardar no envía ningún correo. Después puedes enviar o copiar el código.</p>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-game">Guardar</button><button type="button" class="btn btn-outline-danger" onclick="removeEditedPlayer(this)">Echar de la partida</button><button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('invitation-dialog').close()">Cancelar</button></div>
  </form>
</dialog>
<style>
.planner-dialog {color:var(--text,#eee);background:var(--card,#16213e);border:1px solid #667085;border-radius:18px;padding:24px;width:min(94vw,520px);max-height:90dvh;overflow:auto}
.planner-dialog::backdrop {background:#000a}
.invite-card {padding:14px;border:1px solid #ffffff25;border-radius:12px;min-width:0;background:#ffffff04}
.invite-card .invite-pin {font-size:1.4rem;letter-spacing:.1em;font-variant-numeric:tabular-nums}
.invite-card .invite-actions {display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.invite-card button {min-height:40px}
.invite-actions .btn-outline-secondary,#w-indiv-pins .btn-outline-secondary {color:#e2ecfa;border-color:#8194ad}
.invite-actions .btn-outline-secondary:hover,#w-indiv-pins .btn-outline-secondary:hover {color:#fff;background:#415b7b}
.invite-email {overflow-wrap:anywhere}
#w-pins-grid {display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));justify-content:stretch!important}
.pin-box {font-size:clamp(2rem,8vw,4rem);letter-spacing:.12em}
@media(max-width:480px){#screen-waiting .card.p-4,#screen-setup .card.p-4{padding:16px!important}.planner-dialog{padding:18px}nav button{font-size:.85rem!important}}
</style>
