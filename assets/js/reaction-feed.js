/* Ephemeral, silent reactions, shared by players, control and audio output. */
const ReactionFeed=(()=>{
 const seen=new Set();let overlay;
 function render(state){
  if(!overlay){overlay=document.getElementById('reactions-overlay')||document.createElement('div');overlay.id='reactions-overlay';overlay.setAttribute('aria-hidden','true');if(!overlay.isConnected)document.body.appendChild(overlay);}
  if(!state.reactions_allowed){overlay.replaceChildren();return;}
  const fresh=(state.new_reactions||[]).filter(r=>!seen.has(Number(r.id)));
  for(const r of fresh){seen.add(Number(r.id));if(overlay.childElementCount>=12)continue;const el=document.createElement('div');el.className='flying-reaction';el.style.left=(8+Math.random()*78)+'%';el.textContent=r.emoji;overlay.appendChild(el);setTimeout(()=>el.remove(),2300);}
  if(seen.size>300){const ids=[...seen];for(const id of ids.slice(0,ids.length-100))seen.delete(id);}
 }
 return {render};
})();
