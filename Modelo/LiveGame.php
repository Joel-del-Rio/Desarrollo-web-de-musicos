<?php
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Reaction.php';
final class LiveGame {
    public static function migrate(PDO $db): void {
        $columns=['live_mode'=>'TINYINT NOT NULL DEFAULT 0','live_paused'=>'TINYINT NOT NULL DEFAULT 0','live_pause_at'=>'DATETIME NULL','sound_device'=>'CHAR(64) NULL','sound_seen'=>'DATETIME NULL','sound_status'=>"VARCHAR(20) NOT NULL DEFAULT 'offline'",'sound_message'=>"VARCHAR(160) NOT NULL DEFAULT ''",'sound_label'=>"VARCHAR(60) NOT NULL DEFAULT ''",'music_position'=>'DOUBLE NOT NULL DEFAULT 0','music_started_at'=>'DATETIME NULL','music_playing'=>'TINYINT NOT NULL DEFAULT 0','live_revision'=>'INT NOT NULL DEFAULT 0'];
        $existing=$db->query('SHOW COLUMNS FROM games')->fetchAll(PDO::FETCH_COLUMN);
        foreach($columns as $name=>$type)if(!in_array($name,$existing,true))$db->exec("ALTER TABLE games ADD COLUMN $name $type");
        $db->exec('UPDATE schema_version SET version=30');
    }
    public static function connected(array $g): bool {return !empty($g['sound_device'])&&!empty($g['sound_seen'])&&time()-strtotime($g['sound_seen'].' UTC')<8;}
    public static function requireSound(array $g): void {
        if($g['live_mode'] && (!self::connected($g)||in_array($g['sound_status'],['blocked','offline'],true)))throw new InvalidArgumentException('Activa el equipo de sonido y comprueba su conexión antes de iniciar la canción');
    }
    public static function info(array $g): array {
        return ['connected'=>self::connected($g),'label'=>$g['sound_label'],'status'=>$g['sound_status'],'message'=>$g['sound_message'],'revision'=>(int)$g['live_revision']];
    }
    public static function pause(PDO $db,array $g): void {
        $db->prepare("UPDATE games SET music_position=music_position+IF(music_playing=1 AND music_started_at IS NOT NULL,GREATEST(0,TIMESTAMPDIFF(SECOND,music_started_at,UTC_TIMESTAMP())),0),music_playing=0,music_started_at=NULL,live_paused=IF(status='question',1,live_paused),live_pause_at=IF(status='question',COALESCE(live_pause_at,UTC_TIMESTAMP()),live_pause_at),live_revision=live_revision+1 WHERE id=?")->execute([$g['id']]);
    }
    public static function watchdog(PDO $db,int $id): void {
        $db->beginTransaction();try{
            $s=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$s->execute([$id]);$g=$s->fetch();
            if($g && $g['live_mode'] && $g['status']==='question' && !$g['live_paused'] && !self::connected($g))self::pause($db,$g);
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function request(int $id,string $token,array $data): array {
        $db=Database::getInstance()->pdo();$db->beginTransaction();
        try {
            $s=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$s->execute([$id]);$g=$s->fetch();
            if(!$g||!hash_equals($g['admin_token'],$token))throw new InvalidArgumentException('No autorizado');
            $op=$data['operation']??'';
            if($op==='configure'){
                if($g['status']!=='waiting'||$g['game_type']==='meme')throw new InvalidArgumentException('Configura el sonido antes de empezar una partida de canciones');
                $db->prepare('UPDATE games SET live_mode=?,live_revision=live_revision+1 WHERE id=?')->execute([($data['enabled']??'0')==='1'?1:0,$id]);
            }elseif(!$g['live_mode'])throw new InvalidArgumentException('Activa el modo de evento presencial en el panel');
            elseif(in_array($op,['claim','poll','release'],true)){
                $device=(string)($data['device']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$device))throw new InvalidArgumentException('Equipo no válido');
                if($op==='claim'){
                    if(self::connected($g)&&!hash_equals($g['sound_device'],$device))throw new InvalidArgumentException('Ya hay otro equipo de sonido activo. Desactívalo primero o espera a que se desconecte');
                    $label=mb_substr(trim((string)($data['label']??'Equipo de sonido')),0,60);
                    $db->prepare("UPDATE games SET sound_device=?,sound_label=?,sound_seen=UTC_TIMESTAMP(),sound_status='ready',sound_message='' WHERE id=?")->execute([$device,$label?:'Equipo de sonido',$id]);
                }else{
                    if(!$g['sound_device']||!hash_equals($g['sound_device'],$device))throw new InvalidArgumentException('Este terminal ya no es el equipo de sonido');
                    if($op==='release'){
                        self::pause($db,$g);$db->prepare("UPDATE games SET sound_device=NULL,sound_seen=NULL,sound_status='offline' WHERE id=?")->execute([$id]);
                    }else{
                        $status=$data['sound_status']??'ready';if(!in_array($status,['ready','loading','playing','paused','ended','blocked','error'],true))$status='error';
                        $db->prepare('UPDATE games SET sound_seen=UTC_TIMESTAMP(),sound_status=?,sound_message=? WHERE id=?')->execute([$status,mb_substr((string)($data['message']??''),0,160),$id]);
                        if(in_array($status,['error','blocked'],true)&&$g['status']==='question'&&!$g['live_paused'])self::pause($db,$g);
                    }
                }
            }elseif(in_array($op,['pause','resume','replay'],true)){
                if(!in_array($g['status'],['question','results'],true))throw new InvalidArgumentException('No hay canción activa');
                if((int)($data['expected_round']??-1)!==(int)$g['current_round']||(int)($data['revision']??-1)!==(int)$g['live_revision'])throw new InvalidArgumentException('La partida ha cambiado. Actualiza el mando');
                if($op==='pause')self::pause($db,$g);
                else{
                    self::requireSound($g);
                    $db->prepare("UPDATE games SET question_started_at=IF(live_pause_at IS NOT NULL,DATE_ADD(question_started_at,INTERVAL GREATEST(0,TIMESTAMPDIFF(SECOND,live_pause_at,UTC_TIMESTAMP())) SECOND),question_started_at),live_pause_at=NULL,live_paused=0,music_position=IF(?='replay',0,music_position),music_started_at=UTC_TIMESTAMP(),music_playing=1,live_revision=live_revision+1 WHERE id=?")->execute([$op,$id]);
                }
            }else throw new InvalidArgumentException('Acción no válida');
            $s->execute([$id]);$g=$s->fetch();
            $position=(float)$g['music_position'];if($g['music_playing']&&$g['music_started_at'])$position+=max(0,time()-strtotime($g['music_started_at'].' UTC'));
            $round=(int)$g['current_round'];$song=$db->prepare('SELECT s.id,s.title,s.artist,gs.round_number FROM game_songs gs JOIN songs s ON s.id=gs.song_id WHERE gs.game_id=? AND gs.round_number IN (?,?) ORDER BY gs.round_number');$song->execute([$id,max(1,$round),max(1,$round)+1]);$songs=$song->fetchAll();
            $db->commit();return [...Reaction::state($g),'success'=>true,'status'=>$g['status'],'round'=>$round,'revision'=>(int)$g['live_revision'],'playing'=>(bool)$g['music_playing'],'position'=>$position,'songs'=>$songs,'output'=>self::info($g)];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
