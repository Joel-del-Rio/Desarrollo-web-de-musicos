const soundParams=new URLSearchParams(location.hash.slice(1));
if(soundParams.has('game')&&soundParams.has('key')){sessionStorage.setItem('sound-session',JSON.stringify({game:soundParams.get('game'),key:soundParams.get('key')}));history.replaceState(null,'',location.pathname);}
let soundSession={};try{soundSession=JSON.parse(sessionStorage.getItem('sound-session')||'{}');}catch{}
const outputDevice=Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');
const outputAudio=document.getElementById('event-audio'),outputStatus=document.getElementById('status'),activateButton=document.getElementById('activate');
let unlockingOutput=false,outputActive=false,outputBusy=false,lastOutputContact=0,outputState=null,outputSong=null,outputCode='ready',outputMessage='',wakeLock=null;
const previewCache=new Map(),preloaded=new Map();
outputAudio.volume=.8;
document.getElementById('volume').oninput=e=>outputAudio.volume=Number(e.target.value)/100;
function outputNotice(code,message){outputCode=code;outputMessage=message;outputStatus.textContent=message;activateButton.disabled=outputActive&&!['blocked','error'].includes(code);}
async function outputRequest(operation){
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),3000);
  try{const response=await fetch(SOUND_API+'?action=live_control',{method:'POST',signal:controller.signal,headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({game_id:soundSession.game||'',admin_token:soundSession.key||'',device:outputDevice,operation,label:document.getElementById('device-name').value,sound_status:outputCode,message:outputMessage})});const data=await response.json();if(data.error)throw Error(data.error);return data;}finally{clearTimeout(timeout);}
}
async function preview(song){
  if(!previewCache.has(song.id))previewCache.set(song.id,(async()=>{
    const response=await fetch(SOUND_API+'?action=itunes_preview&song_id='+encodeURIComponent(song.id),{signal:AbortSignal.timeout(9000)});const data=await response.json();
    if(!data.previewUrl)throw Error('Esta canción no tiene audio verificado. La ronda queda en pausa.');
    const media=new Audio();media.preload='auto';media.src=data.previewUrl;preloaded.set(song.id,media);return data.previewUrl;
  })());
  try {return await previewCache.get(song.id);} catch(error){previewCache.delete(song.id);throw error;}
}
function stopOutput(){outputAudio.pause();outputAudio.removeAttribute('src');outputAudio.load();outputSong=null;}
async function applyOutput(state){
  outputState=state;
  ReactionFeed.render(state);
  for(const song of state.songs||[])preview(song).catch(()=>{});
  if(!outputActive)return;
  if(state.status==='waiting'||state.status==='finished'){
    stopOutput();document.getElementById('song').textContent=state.status==='finished'?'Partida terminada':'Esperando al dinamizador';outputNotice('ready',state.status==='finished'?'Partida terminada. Equipo en silencio.':'Equipo activado. Esperando el inicio desde el mando.');return;
  }
  const song=(state.songs||[]).find(s=>Number(s.round_number)===Number(state.round));if(!song)return;
  document.getElementById('song').textContent=`Ronda ${state.round} · ${song.title} — ${song.artist||''}`;
  if(outputSong!==song.id){
    stopOutput();outputSong=song.id;outputNotice('loading','Preparando la canción…');
    try{const url=await preview(song);if(!outputActive||outputSong!==song.id||!outputState||outputState.round!==state.round)return;outputAudio.src=url;outputAudio.load();}
    catch(e){if(outputActive&&outputSong===song.id)outputNotice('error',e.message);return;}
  }
  syncOutput();
}
function syncOutput(){
  const state=outputState;if(!outputActive||!state||performance.now()-lastOutputContact>3500){outputAudio.pause();return;}
  if(!state.playing){outputAudio.pause();if(!['blocked','error'].includes(outputCode))outputNotice('paused',state.status==='results'?'Respuestas cerradas. Silencio para los comentarios del dinamizador.':'Música y ronda en pausa.');return;}
  if(outputAudio.readyState<1)return;
  const pos=Math.max(0,Number(state.position)||0);
  if(Number.isFinite(outputAudio.duration)&&pos>=outputAudio.duration-.15){outputAudio.pause();outputNotice('ended','Fragmento terminado. La partida espera tu decisión desde el mando.');return;}
  if(Math.abs(outputAudio.currentTime-pos)>2)outputAudio.currentTime=pos;
  if(outputAudio.paused&&outputCode!=='blocked')outputAudio.play().then(()=>{if(!outputActive||!outputState?.playing)outputAudio.pause();else outputNotice('playing','Reproduciendo únicamente en este equipo.');}).catch(()=>outputNotice('blocked','El navegador ha bloqueado el audio. Pulsa Activar este equipo y reanuda desde el mando.'));
}
outputAudio.onloadedmetadata=()=>{if(!unlockingOutput)syncOutput();};
outputAudio.onended=()=>{if(outputActive&&!unlockingOutput)outputNotice('ended','Fragmento terminado. La partida no avanza sola.');};
outputAudio.onerror=()=>{if(outputActive&&outputAudio.getAttribute('src'))outputNotice('error','No se pudo reproducir el fragmento. Comprueba la conexión y vuelve a activar el equipo.');};
async function pollOutput(){
  if(!outputActive||outputBusy)return;outputBusy=true;
  try{const state=await outputRequest('poll');if(!outputActive)return;lastOutputContact=performance.now();await applyOutput(state);}
  catch(e){outputAudio.pause();outputStatus.textContent='Conexión interrumpida: música detenida. '+e.message;if(e.message.includes('ya no es')||e.message.includes('No autorizado')||e.message.includes('modo de evento')){outputActive=false;activateButton.disabled=false;}}
  finally{outputBusy=false;}
}
activateButton.onclick=async()=>{
  if(!soundSession.game||!soundSession.key){outputStatus.textContent='Abre el enlace privado del equipo de sonido desde el panel del dinamizador.';return;}
  activateButton.disabled=true;unlockingOutput=true;outputAudio.pause();
  // Unlock this audio element with an inaudible PCM sample inside the user's gesture.
  const wav=new ArrayBuffer(1644),view=new DataView(wav);const write=(at,s)=>{for(let i=0;i<s.length;i++)view.setUint8(at+i,s.charCodeAt(i));};write(0,'RIFF');view.setUint32(4,1636,true);write(8,'WAVEfmt ');view.setUint32(16,16,true);view.setUint16(20,1,true);view.setUint16(22,1,true);view.setUint32(24,8000,true);view.setUint32(28,16000,true);view.setUint16(32,2,true);view.setUint16(34,16,true);write(36,'data');view.setUint32(40,1600,true);
  const silence=URL.createObjectURL(new Blob([wav],{type:'audio/wav'}));outputAudio.src=silence;
  try{
    await outputAudio.play();outputAudio.pause();unlockingOutput=false;URL.revokeObjectURL(silence);
    outputCode='ready';outputMessage='';const state=await outputRequest('claim');outputActive=true;lastOutputContact=performance.now();outputSong=null;previewCache.clear();preloaded.clear();document.getElementById('deactivate').disabled=false;
    if(navigator.wakeLock)try{wakeLock=await navigator.wakeLock.request('screen');}catch{}
    await applyOutput(state);
  }catch(e){unlockingOutput=false;outputActive=false;stopOutput();URL.revokeObjectURL(silence);activateButton.disabled=false;outputStatus.textContent=e.message;}
};
document.getElementById('deactivate').onclick=async()=>{
  outputActive=false;stopOutput();activateButton.disabled=false;document.getElementById('deactivate').disabled=true;try{await outputRequest('release');}catch{}if(wakeLock)await wakeLock.release();outputStatus.textContent='Equipo desactivado. Sin sonido.';
};
setInterval(pollOutput,1000);
setInterval(()=>{if(outputActive&&performance.now()-lastOutputContact>3500){outputAudio.pause();outputStatus.textContent='Sin confirmación del servidor. Música detenida hasta recuperar la conexión.';}},500);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&outputActive){pollOutput();if(navigator.wakeLock)navigator.wakeLock.request('screen').then(lock=>wakeLock=lock).catch(()=>{});}});
window.addEventListener('pagehide',()=>{outputActive=false;outputAudio.pause();});

// Local QR encoding: the game-scoped credential never goes to a QR service.
function renderControlQr() {
  if(!/^[1-9]\d*$/.test(String(soundSession.game||''))||!/^[a-f0-9]{64}$/.test(soundSession.key||''))return;
  const url=new URL(SOUND_API.replace('/Controlador/api.php','')+'/admin',location.origin);
  url.hash=new URLSearchParams({game:soundSession.game,key:soundSession.key}).toString();
  const link=document.getElementById('control-link');link.href=url.href;
  const code=qrcode(0,'M');code.addData(url.href,'Byte');code.make();
  document.getElementById('control-qr').innerHTML=code.createSvgTag({cellSize:5,margin:20,scalable:true});
  document.getElementById('control-access').hidden=false;
}
renderControlQr();
