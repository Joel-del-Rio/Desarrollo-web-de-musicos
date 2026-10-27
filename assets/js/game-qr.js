/* QR access only: never copy PINs, player IDs, host tokens or URL fragments. */
let shareGameId = null;
function publicGameUrl(id) {
  const url = new URL(API.replace(/\/Controlador\/api\.php$/, '') + '/player', location.origin);
  if (/^[1-9]\d*$/.test(String(id))) url.searchParams.set('game', String(id));
  return url.href;
}
function setShareGame(id) { shareGameId = /^[1-9]\d*$/.test(String(id)) ? String(id) : null; }
function openGameQr() {
  const url = publicGameUrl(shareGameId);
  let dialog = document.getElementById('game-qr-dialog');
  if (!dialog) {
    dialog = document.createElement('dialog');
    dialog.id = 'game-qr-dialog';
    dialog.setAttribute('aria-labelledby', 'game-qr-heading');
    dialog.innerHTML = '<button class="btn btn-outline-light qr-close" type="button">Cerrar</button><h2 id="game-qr-heading">Comparte el acceso</h2><p>Escanea el QR con la cámara del móvil. Después, introduce <strong>tu propio código de participación</strong> y tu nombre.</p><div id="game-qr-image" role="img" aria-label="QR de acceso a la partida"></div><p id="game-qr-caption"></p><label for="game-qr-link">Enlace de acceso</label><input id="game-qr-link" readonly><div class="qr-actions"><button id="game-qr-copy" class="btn btn-game" type="button">Copiar enlace</button><button id="game-qr-share" class="btn btn-outline-light" type="button">Compartir…</button></div><p id="game-qr-feedback" role="status"></p>';
    document.body.appendChild(dialog);
    dialog.querySelector('.qr-close').onclick = () => dialog.close();
    document.getElementById('game-qr-copy').onclick = async () => {
      const input = document.getElementById('game-qr-link');
      try { await navigator.clipboard.writeText(input.value); document.getElementById('game-qr-feedback').textContent = 'Enlace copiado. Cada persona usa su propio código.'; }
      catch { input.focus(); input.select(); document.getElementById('game-qr-feedback').textContent = 'Mantén pulsado el enlace seleccionado para copiarlo.'; }
    };
    document.getElementById('game-qr-share').hidden = !navigator.share;
    document.getElementById('game-qr-share').onclick = async () => {
      try { await navigator.share({title:'Entrar a Hitstoric',text:'Abre la partida e introduce tu propio código de participación.',url:document.getElementById('game-qr-link').value}); }
      catch(e) { if(e.name!=='AbortError') document.getElementById('game-qr-feedback').textContent='Puedes copiar el enlace o enseñar el QR.'; }
    };
  }
  document.getElementById('game-qr-link').value = url;
  document.getElementById('game-qr-caption').textContent = shareGameId ? 'Acceso a la partida · ' + shareGameId : 'Acceso a Hitstoric';
  document.getElementById('game-qr-feedback').textContent = '';
  const image = document.getElementById('game-qr-image');
  try { const code = qrcode(0, 'M'); code.addData(url, 'Byte'); code.make(); image.innerHTML = code.createSvgTag({cellSize:6,margin:24,scalable:true}); }
  catch { image.textContent = 'No se ha podido dibujar el QR. Puedes copiar el enlace.'; }
  if (!dialog.open) dialog.showModal();
}
setShareGame(new URLSearchParams(location.search).get('game'));
