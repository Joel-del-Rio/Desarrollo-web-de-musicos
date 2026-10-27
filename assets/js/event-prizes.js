/* Per-game rewards; the server is the authority for eligibility and stock. */
let eventPrizeSignature = '', eventPrizeState = null;
function prizeRule(p) {
  return p.rule==='rank' ? `Puesto ${p.target} de la clasificación final` : p.rule==='streak' ? `Racha de ${p.target} aciertos consecutivos` : `${p.target} aciertos acumulados`;
}
function prizeNode(tag,text,className='') { const n=document.createElement(tag);n.textContent=text;n.className=className;return n; }
function renderEventPrizes(state,admin) {
  eventPrizeState=state;
  let panel=document.getElementById('event-prizes');
  if(!panel){
    panel=document.createElement('section');panel.id='event-prizes';panel.className='container py-3';panel.style.maxWidth='960px';
    document.body.append(panel);
    const style=document.createElement('style');style.textContent='#event-prizes article{border:1px solid #ffffff40;border-radius:16px;padding:16px;min-width:0;overflow-wrap:anywhere;background:#16213e;color:#f7f7fc}#event-prizes .prize-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:12px}#event-prizes button{min-height:44px}#prize-editor{color:#fff;background:#16213e;border:1px solid #8894a8;border-radius:18px;padding:22px;width:min(94vw,560px);max-height:92dvh;overflow:auto}#prize-editor::backdrop{background:#000b}#prize-editor label{display:block;margin-top:12px}#prize-editor input,#prize-editor select,#prize-editor textarea{width:100%;padding:9px;border-radius:8px;background:#fff;color:#12182a;border:1px solid #aaa}#prize-editor input[type=checkbox]{width:auto}.prize-win{color:#ffe68a;font-weight:700;white-space:pre-line}';document.head.append(style);
  }
  panel.hidden=false;
  const data=state.event_prizes||{items:[],awards:[]};
  const signature=JSON.stringify([data,state.status,state.session_kind,state.player?.id,admin]);if(signature===eventPrizeSignature)return;eventPrizeSignature=signature;
  panel.replaceChildren();if(!data.items.length){panel.hidden=true;return;}
  panel.append(prizeNode('h2','Premios de la partida definitiva','h4'));
  if(state.session_kind==='tutorial')panel.append(prizeNode('p','Durante el tutorial no se ganan premios. Los puntos y las rachas empezarán de cero en la partida definitiva.','fw-bold text-warning'));
  const own=data.awards.filter(a=>Number(a.player_id)===Number(state.player?.id));
  const unseen=own.filter(a=>!sessionStorage.getItem('event-award-'+a.id));
  if(unseen.length&&!document.getElementById('prize-won-dialog')?.open){
    document.getElementById('prize-won-dialog')?.remove();
    const notice=document.createElement('dialog');notice.id='prize-won-dialog';notice.style.cssText='color:#fff;background:#16213e;border:2px solid #f5d67a;border-radius:20px;padding:24px;width:min(92vw,480px);max-height:90dvh;overflow:auto';
    notice.append(prizeNode('h2','¡Has ganado un premio!','h3'));
    for(const a of unseen){const p=data.items.find(p=>Number(p.id)===Number(a.prize_id));notice.append(prizeNode('h3',p.title,'h5'),prizeNode('p',p.products));sessionStorage.setItem('event-award-'+a.id,'1');}
    notice.append(prizeNode('p','Recógelo en la barra mostrando tu código de participación.'));
    const close=prizeNode('button','¡Entendido!','btn btn-game');close.onclick=()=>notice.close();notice.append(close);document.body.append(notice);notice.showModal();
  }
  if(own.length){const alert=prizeNode('p',own.map(a=>{const p=data.items.find(p=>Number(p.id)===Number(a.prize_id));return `¡Has ganado ${p?.title||'un premio'}! ${p?.products||''}${a.delivered_at?' · Entregado':' · Recógelo en la barra mostrando tu código de participación.'}`;}).join('\n'),'prize-win');alert.setAttribute('role','status');panel.append(alert);}
  panel.append(prizeNode('p','Racha y aciertos: ganan los primeros en alcanzar el objetivo, hasta agotar las unidades. Se anuncian al cerrar la ronda. Los bots no participan; una persona puede ganar varios premios.','small'));
  const grid=prizeNode('div','','prize-grid');panel.append(grid);
  for(const p of data.items){
    if(!Number(p.enabled)&&!admin)continue;
    const card=prizeNode('article','');grid.append(card);
    card.append(prizeNode('h3',p.title,'h5'),prizeNode('p',p.products),prizeNode('p',prizeRule(p),'fw-bold'));
    const wins=data.awards.filter(a=>Number(a.prize_id)===Number(p.id));
    card.append(prizeNode('p',Number(p.enabled)?`${wins.length} / ${p.quantity} unidades adjudicadas`:'Desactivado','small'));
    for(const a of wins){
      card.append(prizeNode('p',`${a.player_name} · participante #${a.player_id} · ronda ${a.round_number}${admin&&a.participation_pin?' · Código '+a.participation_pin:''}${a.delivered_at?' · Entregado':' · Pendiente de entrega'}`,'prize-win'));
      if(admin&&!a.delivered_at){const b=prizeNode('button','Marcar entregado','btn btn-outline-light');b.onclick=async()=>{if(!confirm('Confirma la entrega física a '+a.player_name+'. Comprueba su código de participación: '+(a.participation_pin||'sala con PIN compartido: verifica su identidad')+'.'))return;b.disabled=true;try{const r=await apiPost('manage_prizes',{operation:'deliver',award_id:a.id});if(r.error)throw Error(r.error);applyState(await fetchState());}catch(e){alert(e.message);b.disabled=false;}};card.append(b);}
    }
    if(admin&&state.status==='waiting'){const b=prizeNode('button','Configurar premio','btn btn-outline-light');b.onclick=()=>editEventPrize(p);card.append(b);}
  }
  panel.append(prizeNode('p','Clasificación de premios: puntos entre participantes que hayan respondido. Empate: más aciertos; después, quien registró antes su último acierto. Sin respuesta, no se obtiene premio final.','small'));
  if(admin)panel.append(prizeNode('p',state.status==='waiting'?'Configura los productos antes de empezar. Si hay varias unidades, cada ganador recibe los productos indicados. Los cambios se guardan para esta partida. El cobro de la participación se gestiona fuera de la aplicación.':'Las condiciones quedan cerradas al empezar. Puedes registrar las entregas desde el móvil o el ordenador.','small'));
}
function editEventPrize(p){
  document.getElementById('prize-editor')?.remove();
  const dialog=document.createElement('dialog');dialog.id='prize-editor';
  const form=document.createElement('form');dialog.append(form);form.append(prizeNode('h2','Configurar premio','h4'));
  function input(label,name,value,type='text',max){const l=prizeNode('label',label);const n=document.createElement('input');n.name=name;n.value=value;n.type=type;if(type==='number'){n.min='1';n.max=max;}else n.maxLength=120;l.append(n);form.append(l);return n;}
  input('Nombre del premio','title',p.title).required=true;
  const label=prizeNode('label','Productos que recibe cada ganador');const products=document.createElement('textarea');products.name='products';products.rows=3;products.maxLength=2000;products.value=p.products;label.append(products);form.append(label);
  const rl=prizeNode('label','Condición');const rule=document.createElement('select');rule.name='rule';for(const [value,title] of [['streak','Racha de aciertos consecutivos'],['correct','Número de aciertos acumulados'],['rank','Puesto en la clasificación final']]){const o=prizeNode('option',title);o.value=value;rule.append(o);}rule.value=p.rule;rl.append(rule);form.append(rl);
  const target=input('Objetivo (aciertos, racha o puesto)','target',p.target,'number',10000);
  const quantity=input('Unidades disponibles','quantity',p.quantity,'number',500);
  const adjust=()=>{target.max=rule.value==='rank'?10000:eventPrizeState.total_rounds;quantity.disabled=rule.value==='rank';if(quantity.disabled)quantity.value=1;};rule.onchange=adjust;adjust();
  const enabled=input(' Premio activo','enabled','1','checkbox');enabled.checked=!!Number(p.enabled);
  const error=prizeNode('p','','text-warning');error.setAttribute('role','alert');form.append(error);
  const save=prizeNode('button','Guardar','btn btn-game mt-3');save.type='submit';const close=prizeNode('button','Cancelar','btn btn-outline-light mt-3 ms-2');close.type='button';close.onclick=()=>dialog.close();form.append(save,close);
  form.onsubmit=async e=>{e.preventDefault();save.disabled=true;error.textContent='';try{const values=Object.fromEntries(new FormData(form));values.enabled=enabled.checked?'1':'0';values.quantity=quantity.value;const r=await apiPost('manage_prizes',{prize_id:p.id,...values});if(r.error)throw Error(r.error);dialog.close();applyState(await fetchState());}catch(e){error.textContent=e.message;}finally{save.disabled=false;}};
  document.body.append(dialog);dialog.showModal();
}
