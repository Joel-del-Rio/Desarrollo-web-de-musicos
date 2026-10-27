/* Persistent organizer workspace. Only its random secret grants access; emails are never credentials. */
const HOST_KEY = 'hitstoric_host_key';
let hostKey = localStorage.getItem(HOST_KEY);
let inviteRows = [], invitePage = 0, inviteSignature = '', lastRosterFetch = 0;
let creatingGame = false;
function prepareHost() {
  const controlParams=new URLSearchParams(location.hash.slice(1));
  const controlGame=controlParams.get('game'),controlKey=controlParams.get('key');
  if (/^[1-9]\d*$/.test(controlGame||'') && /^[a-f0-9]{64}$/.test(controlKey||'')) {
    gameId=controlGame;adminToken=controlKey;
    localStorage.setItem(GK,gameId);localStorage.setItem(TK,adminToken);
    history.replaceState(null,'',location.pathname+location.search);
  }
  const imported = new URLSearchParams(location.hash.slice(1)).get('host');
  if (/^[a-f0-9]{64}$/.test(imported || '')) {
    if (hostKey !== imported) clearSession();
    hostKey = imported;
    history.replaceState(null, '', location.pathname + location.search);
  }
  if (!/^[a-f0-9]{64}$/.test(hostKey || '')) hostKey = Array.from(crypto.getRandomValues(new Uint8Array(32)), b => b.toString(16).padStart(2, '0')).join('');
  localStorage.setItem(HOST_KEY, hostKey);
  document.getElementById('invitation-edit-form').addEventListener('submit', async e => {
    e.preventDefault(); const button=e.submitter; button.disabled=true;
    try {
      if(document.getElementById('edit-player-id').value) await manage('player',{player_id:document.getElementById('edit-player-id').value,name:document.getElementById('edit-invitation-name').value});
      else await manage('edit',{invitation_id:document.getElementById('edit-invitation-id').value,name:document.getElementById('edit-invitation-name').value,email:document.getElementById('edit-invitation-email').value});
      document.getElementById('invitation-dialog').close();
    } catch(e){showAdminError(e.message);} finally {button.disabled=false;}
  });
  if (gameId && adminToken) hostRequest('host_attach',{game_id:gameId,admin_token:adminToken}).catch(e=>showAdminError(e.message));
  return Boolean(imported);
}
async function hostRequest(action, values={}) {
  const response=await fetch(`${API}?action=${action}`,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({host_key:hostKey,...values})});
  const data=await response.json(); if(data.error) throw new Error(data.error); return data;
}
async function openSavedGames() {
  stopPolling(); stopTimer(); hideMemePlayer('q-meme-img'); hideMemePlayer('r-meme-img'); lastStatus=null;
  showScreen('library'); const list=document.getElementById('saved-games');list.textContent='Cargando partidas…';
  try {
    if(gameId && adminToken) await hostRequest('host_attach',{game_id:gameId,admin_token:adminToken});
    const {games}=await hostRequest('host_games');
    list.replaceChildren();
    if(!games.length){list.textContent='Todavía no hay partidas guardadas. Prepara la primera cuando quieras.';return;}
    for(const game of games){
      const card=document.createElement('article');card.className='card p-3';
      const status={waiting:'Preparada',question:'En juego',results:'En juego',finished:'Finalizada'}[game.status]||game.status;
      card.innerHTML=`<h2 class="h5">${esc(game.title||`Partida ${game.pin}`)}</h2><p class="text-secondary mb-2">${esc(status)} · ${Number(game.invited_count)} invitaciones · ${Number(game.player_count)} jugadores dentro</p><p class="small">${game.planned_at?esc(game.planned_at.slice(0,16).replace(' ',' · '))+' (Madrid)':'Sin fecha fijada'}</p>`;
      const button=document.createElement('button');button.className='btn btn-outline-light align-self-start';button.textContent=game.status==='finished'?'Ver resultado':'Abrir partida';
      button.addEventListener('click',()=>resumeSavedGame(game));card.append(button);list.append(card);
    }
  }catch(e){list.textContent='No se pudieron cargar las partidas: '+e.message;}
}
async function resumeSavedGame(game){
  stopPolling();gameId=String(game.id);adminToken=game.admin_token;lastStatus=null;lastQuestionRound=-1;lastRosterFetch=0;inviteSignature='';invitePage=0;
  localStorage.setItem(GK,gameId);localStorage.setItem(TK,adminToken);localStorage.setItem(GK+'_pin',game.pin);localStorage.setItem(GK+'_pinMode',game.pin_mode);
  try{const state=await fetchState();if(!state||state.error)throw new Error(state?.error||'Sin conexión');applyState(state);}catch(e){showAdminError(e.message);}
}
function showHostLink(){
  document.getElementById('host-link').value=API.replace('/Controlador/api.php','')+'/admin#host='+hostKey;
  document.getElementById('host-link-dialog').showModal();
}
async function copyText(text){
  try{await navigator.clipboard.writeText(text);showAdminNotice('Copiado');return true;}
  catch{const field=document.createElement('textarea');field.value=text;document.body.append(field);field.select();const ok=document.execCommand('copy');field.remove();showAdminNotice(ok?'Copiado':'No se pudo copiar. Selecciona el código y cópialo manualmente.');return ok;}
}
function showAdminNotice(message){showAdminError(message);}
async function manage(operation,values={}){
  const data=await apiPost('manage_game',{operation,...values});if(data.error)throw new Error(data.error);
  lastRosterFetch=0;const state=await fetchState();if(state&&!state.error)applyState(state);return data;
}
async function savePlannedDetails(button){
  button.disabled=true;try{await manage('details',{title:document.getElementById('waiting-title').value,planned_at:document.getElementById('waiting-date').value});showAdminNotice('Cambios guardados');}catch(e){showAdminError(e.message);}finally{button.disabled=false;}
}
async function addInvitations(event){
  event.preventDefault();const button=event.submitter;button.disabled=true;
  try{
    const count=Number(document.getElementById('add-invite-count').value);
    await manage('add',{count,name:document.getElementById('add-invite-name').value,email:document.getElementById('add-invite-email').value,send_email:document.getElementById('add-invite-send').checked?'1':'0'});
    document.getElementById('add-invite-name').value='';document.getElementById('add-invite-email').value='';
    showAdminNotice(`${count} código${count===1?'':'s'} guardado${count===1?'':'s'}`);
  }catch(e){showAdminError(e.message);}finally{button.disabled=false;}
}
function refreshPlanning(state){
  if(state.pin){localStorage.setItem(GK+'_pin',state.pin);document.getElementById('w-pin').textContent=state.pin;}
  const title=document.getElementById('waiting-title'), date=document.getElementById('waiting-date');
  const nextTitle=state.title||'',nextDate=(state.planned_at||'').slice(0,16).replace(' ','T');
  if(title.dataset.game!==gameId || (title.value===title.dataset.saved && document.activeElement!==title))title.value=nextTitle;
  if(title.dataset.game!==gameId || (date.value===date.dataset.saved && document.activeElement!==date))date.value=nextDate;
  title.dataset.game=gameId;title.dataset.saved=nextTitle;date.dataset.saved=nextDate;
  document.getElementById('w-indiv-pins').classList.toggle('d-none',state.pin_mode!=='individual');
  document.getElementById('w-pin-card').classList.toggle('d-none',state.pin_mode==='individual');
  if(state.invitations){
    inviteRows=state.invitations;
    localStorage.setItem(GK+'_indivPins',JSON.stringify(inviteRows.map(i=>i.pin)));
    const signature=JSON.stringify(inviteRows);if(signature!==inviteSignature){inviteSignature=signature;renderInvitations();}
  }
}
function renderInvitations(){
  const query=document.getElementById('invite-search').value.toLowerCase().trim();
  const rows=inviteRows.filter(i=>`${i.display_name} ${i.email||''} ${i.pin}`.toLowerCase().includes(query));
  const pages=Math.max(1,Math.ceil(rows.length/40));invitePage=Math.min(invitePage,pages-1);
  document.getElementById('invite-count').textContent=`${inviteRows.length} códigos · ${inviteRows.filter(i=>Number(i.used)).length} utilizados`;
  document.getElementById('invite-page').textContent=`Página ${invitePage+1} de ${pages}`;
  document.getElementById('invite-prev').disabled=invitePage===0;document.getElementById('invite-next').disabled=invitePage>=pages-1;
  const grid=document.getElementById('w-pins-grid');grid.replaceChildren();
  const labels={queued:'En cola',retry:'Reintento pendiente',sending:'Enviando',sent:'Aceptado por el servidor de correo',failed:'Envío fallido: puedes reintentarlo',unknown:'Entrega sin confirmar',cancelled:'Envío cancelado'};
  for(const invite of rows.slice(invitePage*40,invitePage*40+40)){
    const card=document.createElement('article');card.className='invite-card';
    card.innerHTML=`<strong>${esc(invite.display_name||'Sin nombre asignado')}</strong><div class="invite-pin">${esc(invite.pin)}</div><div class="small invite-email text-secondary">${esc(invite.email||'Sin correo · entrega el código en persona')}</div><div class="small mt-1">${Number(invite.used)?'Ya está en la sala':'Pendiente de entrar'}${invite.mail_status?' · '+esc(labels[invite.mail_status]||invite.mail_status):''}</div>`;
    const actions=document.createElement('div');actions.className='invite-actions';
    const action=(label,fn)=>{const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-secondary';b.textContent=label;b.addEventListener('click',async()=>{b.disabled=true;try{await fn();}catch(e){showAdminError(e.message);}finally{b.disabled=false;}});actions.append(b);return b;};
    action('Copiar código',()=>copyText(invite.pin));
    action('Copiar enlace',()=>copyText(API.replace('/Controlador/api.php','')+'/player?pin='+invite.pin));
    action('Editar',()=>{document.getElementById('edit-player-id').value='';document.getElementById('edit-invitation-email').disabled=false;document.getElementById('edit-invitation-id').value=invite.id;document.getElementById('edit-invitation-name').value=invite.display_name;document.getElementById('edit-invitation-email').value=invite.email||'';document.getElementById('invitation-dialog').showModal();});
    if(invite.email)action(invite.mail_status==='sent'?'Reenviar correo':'Enviar correo',()=>manage('send',{invitation_id:invite.id}));
    if(!Number(invite.used))action('Anular código',async()=>{if(confirm('¿Anular este código? Dejará de permitir el acceso.'))await manage('revoke',{invitation_id:invite.id});});
    card.append(actions);grid.append(card);
  }
  if(!rows.length)grid.textContent=inviteRows.length?'No hay coincidencias.':'Añade jugadores o genera códigos para repartirlos en persona.';
}
async function editConnectedPlayer(id,name){
 document.getElementById('edit-player-id').value=id;document.getElementById('edit-invitation-id').value='';document.getElementById('edit-invitation-name').value=name;document.getElementById('edit-invitation-email').value='';document.getElementById('edit-invitation-email').disabled=true;document.getElementById('invitation-dialog').showModal();
}
async function cancelPlannedGame(){
  if(!confirm('¿Cerrar esta partida? Los códigos dejarán de aceptar jugadores. El registro se conservará en Mis partidas.'))return;
  try{await manage('cancel');await openSavedGames();}catch(e){showAdminError(e.message);}
}

async function removeEditedPlayer(button){
 const name=document.getElementById('edit-invitation-name').value||'esta persona';
 if(!confirm('¿Echar a '+name+' de la partida? Su código individual quedará anulado.'))return;
 button.disabled=true;
 try{const pid=document.getElementById('edit-player-id').value;
 await manage(pid?'remove_player':'remove',pid?{player_id:pid}:{invitation_id:document.getElementById('edit-invitation-id').value});
 document.getElementById('invitation-dialog').close();showAdminNotice('Participante retirado de la partida');
 }catch(e){showAdminError(e.message);}finally{button.disabled=false;}
}
