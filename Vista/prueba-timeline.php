<?php
// Standalone interface rehearsal: no database access, player identity or real answers.
ob_start();require __DIR__.'/player.php';$html=ob_get_clean();
$state=['status'=>'question','live_mode'=>1,'live_paused'=>0,'has_answered'=>false,'current_round'=>1,'total_rounds'=>1,'question_time'=>30,'time_left'=>30,'song'=>['title'=>'La canción que quieres colocar','artist'=>'Prueba el gesto · sin música'],'player'=>['id'=>0,'name'=>'Prueba de interfaz','score'=>0,'streak'=>0],'timeline'=>[],'leaderboard'=>[],'new_reactions'=>[],'event_prizes'=>['items'=>[],'awards'=>[]]];
foreach([[1965,'Help!','The Beatles'],[1976,'Dancing Queen','ABBA'],[1984,'Jump','Van Halen'],[1991,'Smells Like Teen Spirit','Nirvana'],[1998,'Clandestino','Manu Chao'],[2003,'Besame','El Canto del Loco'],[2010,'Waka Waka','Shakira'],[2017,'Despacito','Luis Fonsi']] as $s)$state['timeline'][]=['year'=>$s[0],'title'=>$s[1],'artist'=>$s[2]];
$html=str_replace(["'hitstoric_pid'","'hitstoric_gid_p'"],["'hitstoric_demo_pid'","'hitstoric_demo_gid'"],$html);
$boot='<script>const demoState='.json_encode($state,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.fetch=async()=>({json:async()=>demoState});</script>';
$html=str_replace('<script src="'.BASE_URL.'/assets/js/player.js',$boot.'<script src="'.BASE_URL.'/assets/js/player.js',$html);
$tail='<script>stopPolling();playerId=null;applyState(demoState);stopPolling();document.querySelector(".player-toolbar").innerHTML="<strong>PRUEBA DE INTERFAZ · 8 canciones</strong>";document.querySelectorAll(".back-btn").forEach(b=>b.hidden=true);document.getElementById("confirm-btn").onclick=()=>{if(selectedPos===null||timelineSettling)return;document.getElementById("confirm-hint").textContent="✓ Confirmado: "+positionLabel(selectedPos)+". Puedes seguir probando.";};</script>';
echo str_replace('</body>',$tail.'</body>',$html);

