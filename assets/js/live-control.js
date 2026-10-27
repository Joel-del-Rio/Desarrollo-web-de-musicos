let liveMode=false, livePaused=false, liveControlState=null;
function renderLiveControl(state){
  liveMode=!!Number(state.live_mode);livePaused=!!Number(state.live_paused);liveControlState=state;
  let panel=document.getElementById('live-control');
  if(!panel){panel=document.createElement('section');panel.id='live-control';panel.className='container py-3';panel.style.maxWidth='960px';document.querySelector('.screen').before(panel);}
  panel.hidden=state.game_type==='meme';if(panel.hidden)return;
  if(liveMode){for(const a of document.querySelectorAll('audio,video')){a.pause();a.removeAttribute('src');}stopTimer();}
  const output=state.live_output||{};
  const status={ready:'Listo',loading:'Cargando música',playing:'Reproduciendo',paused:'En pausa',ended:'Fragmento terminado · no avanza solo',blocked:'Falta activar audio en el equipo',error:'No se pudo cargar el audio',offline:'Sin conexión'}[output.status]||'Sin conexión';
  const signature=JSON.stringify([gameId,liveMode,livePaused,state.status,state.current_round,state.answer_count,state.player_count,output]);
  if(panel.dataset.signature===signature)return;panel.dataset.signature=signature;panel.replaceChildren();
  const heading=document.createElement('h2');heading.className='h5';heading.textContent=liveMode?'Mando del dinamizador · este terminal está en silencio':'Sonido y control del evento';panel.append(heading);
  const text=document.createElement('p');text.className='small';text.textContent=liveMode?'La música sale únicamente por el equipo de sonido activado. Tú decides cuándo cerrar respuestas y pasar de canción.':'Activa el modo presencial para separar el equipo de sonido del mando y avanzar manualmente.';panel.append(text);
  const actions=document.createElement('div');actions.className='d-flex flex-wrap gap-2';panel.append(actions);
  function button(label,fn){const b=document.createElement('button');b.className='btn btn-outline-light';b.style.minHeight='44px';b.textContent=label;b.onclick=async()=>{b.disabled=true;try{await fn();}catch(e){showAdminError(e.message);}finally{b.disabled=false;}};actions.append(b);return b;}
  if(state.status==='waiting')button(liveMode?'Desactivar modo presencial':'Activar modo presencial',()=>liveCommand('configure',{enabled:liveMode?'0':'1'}));
  if(!liveMode)return;
  const connection=document.createElement('p');connection.className='mt-3 fw-bold';connection.setAttribute('role','status');connection.textContent=output.connected?`Equipo: ${output.label} · ${status}`:'Equipo de sonido desconectado. Actívalo antes de iniciar o reanudar.';panel.append(connection);
  if(output.message){const message=document.createElement('p');message.textContent=output.message;panel.append(message);}
  button('Enlace del equipo de sonido',()=>showSoundLink());
  button('Abrir sonido en este equipo',()=>{window.open(soundLink(),'_blank','noopener');});
  if(['question','results'].includes(state.status)){
    button('Pausar música y ronda',()=>liveCommand('pause'));
    button('Reanudar música y ronda',()=>liveCommand('resume'));
    button('Repetir fragmento',()=>liveCommand('replay'));
  }
  if(state.status==='question'){
    const progress=document.createElement('p');progress.className='mt-3 mb-0 fw-bold';const total=Number(state.player_count||0),answered=Number(state.answer_count||0);progress.textContent=livePaused?'Partida en pausa: no se admiten respuestas.':`${answered} de ${total} han respondido. ${total>0&&answered>=total?'Ya han respondido todos. Puedes cerrar la ronda.':'Respuestas abiertas hasta que las cierres.'}`;panel.append(progress);
    button('Cerrar respuestas y parar música',()=>showResults());
  }
  if(state.status==='results')button(Number(state.current_round)>=Number(state.total_rounds)?(state.session_kind==='tutorial'?'Terminar tutorial y volver a la sala':'Finalizar partida'):'Siguiente canción',()=>nextRound());
}
async function liveCommand(operation,extra={}){const s=liveControlState;const result=await apiPost('live_control',{operation,expected_round:s.current_round,revision:s.live_output?.revision,...extra});if(result.error)throw Error(result.error);const next=await fetchState();if(next&&!next.error)applyState(next);}
function soundLink(){return API.replace('/Controlador/api.php','')+'/Vista/sound.php#'+new URLSearchParams({game:gameId,key:adminToken});}
function showSoundLink(){
  document.getElementById('sound-link-dialog')?.remove();const d=document.createElement('dialog');d.id='sound-link-dialog';d.className='planner-dialog';
  const h=document.createElement('h2');h.className='h5';h.textContent='Conectar a la mesa de sonido';
  const p=document.createElement('p');p.textContent='Abre este enlace privado en el ordenador, tablet o móvil conectado a la mesa y pulsa «Activar este equipo». Mantén esa página abierta. Este mando seguirá en silencio. No compartas el enlace con jugadores.';
  const field=document.createElement('textarea');field.className='form-control mb-3';field.rows=4;field.readOnly=true;field.value=soundLink();
  const copy=document.createElement('button');copy.className='btn btn-game';copy.textContent='Copiar enlace';copy.onclick=()=>copyText(field.value);
  const close=document.createElement('button');close.className='btn btn-outline-light ms-2';close.textContent='Cerrar';close.onclick=()=>d.close();d.append(h,p,field,copy,close);document.body.append(d);d.showModal();
}
