<?php require_once __DIR__.'/../config.php'; header('Cache-Control: no-store, no-cache, must-revalidate'); header('Referrer-Policy: no-referrer'); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hitstoric · Equipo de sonido</title>
<style>body{margin:0;background:#151629;color:#f6f6fb;font:18px system-ui,sans-serif}main{max-width:680px;margin:auto;padding:30px 20px}button,input{font:inherit;padding:14px;border-radius:12px;margin:8px 0;border:1px solid #8992ac}button{background:#ea3c65;color:white;cursor:pointer}button:disabled{opacity:.5}input{box-sizing:border-box;width:100%}#status{padding:20px;border:1px solid #8992ac;border-radius:14px;white-space:pre-line}small{display:block;color:#c7ccdd;line-height:1.6}.actions{display:flex;gap:12px;flex-wrap:wrap}a{color:#a9d5ff}</style><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reactions.css?v=20261024-reactions1">
<script src="<?= BASE_URL ?>/assets/js/reaction-feed.js?v=20261024-reactions1"></script>
</head><body><main>
<h1>Equipo de sonido</h1><p>Solo este terminal reproduce música por la mesa de sonido. Controla la partida desde el panel del dinamizador en tu otro dispositivo.</p>
<label for="device-name">Nombre para identificar este equipo</label><input id="device-name" maxlength="60" value="Equipo conectado a la mesa" autocomplete="off">
<div class="actions"><button id="activate">Activar este equipo</button><button id="deactivate" disabled>Desactivar sonido</button></div>
<p id="status" role="status">Pendiente de activar. No sonará nada hasta que comience una canción.</p>
<h2 id="song">Esperando partida</h2><label for="volume">Volumen de salida</label><input id="volume" type="range" min="0" max="100" value="80">
<section id="control-access" hidden style="padding:20px 0">
<h2>Abre el mando en tu móvil</h2><p>Escanea este QR para controlar esta partida. El móvil del mando no reproduce música.</p>
<div id="control-qr" role="img" aria-label="QR privado del mando del anfitrión" style="background:white;padding:12px;max-width:260px;border-radius:12px"></div>
<p><a id="control-link" target="_blank" rel="noopener noreferrer">Abrir mando de esta partida</a></p>
<small>QR privado del anfitrión: permite controlar la partida. No es el QR de acceso de los jugadores.</small>
</section>
<audio id="event-audio" preload="auto" playsinline></audio>
<small>Mantén esta página abierta y el dispositivo despierto, conectado a la corriente. Comprueba el volumen de la mesa antes del evento. Los fragmentos disponibles duran aproximadamente 30 segundos; al terminar no se avanza de canción. Si se pierde la conexión, el equipo deja de sonar.</small>
</main><script>const SOUND_API=<?= json_encode(BASE_URL.'/Controlador/api.php') ?>;</script><script src="<?= BASE_URL ?>/assets/vendor/qrcode-1.4.4.js"></script><script src="<?= BASE_URL ?>/assets/js/sound-output.js?v=20261024-reactions1"></script></body></html>
