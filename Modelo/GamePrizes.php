<?php
require_once __DIR__.'/Database.php';
final class GamePrizes {
    public static function migrate(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS game_prizes (id INT AUTO_INCREMENT PRIMARY KEY, game_id INT NOT NULL, slot INT NOT NULL, title VARCHAR(120) NOT NULL, products TEXT NOT NULL, rule VARCHAR(16) NOT NULL, target INT NOT NULL, quantity INT NOT NULL DEFAULT 1, enabled TINYINT NOT NULL DEFAULT 1, UNIQUE KEY game_slot(game_id,slot)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS game_awards (id INT AUTO_INCREMENT PRIMARY KEY, game_id INT NOT NULL, prize_id INT NOT NULL, player_id INT NOT NULL, round_number INT NOT NULL, delivered_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY prize_player(prize_id,player_id), INDEX game_awards(game_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS game_prize_checks (game_id INT NOT NULL, phase VARCHAR(24) NOT NULL, PRIMARY KEY(game_id,phase)) ENGINE=InnoDB");
        $db->exec('UPDATE schema_version SET version=29');
    }
    public static function defaults(PDO $db, array $g): void {
        if ($g['status']!=='waiting') return;
        $existing=$db->prepare('SELECT COUNT(*) FROM game_prizes WHERE game_id=?');$existing->execute([$g['id']]);if((int)$existing->fetchColumn()===3)return;
        $s=$db->prepare('INSERT IGNORE INTO game_prizes(game_id,slot,title,products,rule,target) VALUES (?,?,?,?,?,?)');
        $rounds=(int)$g['total_rounds'];
        foreach ([['Camiseta del Rocantrini','1 camiseta del Rocantrini','streak',min(5,(int)ceil($rounds/2))],['Premio de las paradas','Producto de una parada · pendiente de concretar','correct',min(8,(int)ceil($rounds*.8))],['Gran premio de las paradas','Lote especial de las paradas · pendiente de concretar','rank',1]] as $i=>$p) $s->execute(array_merge([$g['id'],$i+1],$p));
    }
    public static function manage(int $id,string $token,array $data): array {
        $db=Database::getInstance()->pdo(); $db->beginTransaction();
        try {
            $s=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$s->execute([$id]);$g=$s->fetch();
            if (!$g || !hash_equals($g['admin_token'],$token)) throw new InvalidArgumentException('No autorizado');
            if (($data['operation']??'')==='deliver') {
                $db->prepare('UPDATE game_awards SET delivered_at=COALESCE(delivered_at,UTC_TIMESTAMP()) WHERE id=? AND game_id=?')->execute([(int)($data['award_id']??0),$id]);
            } else {
                if ($g['status']!=='waiting') throw new InvalidArgumentException('Los premios se configuran antes de empezar');
                self::defaults($db,$g);
                $exists=$db->prepare('SELECT 1 FROM game_prizes WHERE id=? AND game_id=?');$exists->execute([(int)($data['prize_id']??0),$id]);if(!$exists->fetchColumn())throw new InvalidArgumentException('Premio no encontrado');
                $title=trim((string)($data['title']??''));$products=trim((string)($data['products']??''));$rule=$data['rule']??'';
                $target=filter_var($data['target']??0,FILTER_VALIDATE_INT);$quantity=filter_var($data['quantity']??0,FILTER_VALIDATE_INT);
                if (!$title || mb_strlen($title)>120 || mb_strlen($products)>2000 || !in_array($rule,['streak','correct','rank'],true) || !$target || $target<1 || $target>($rule==='rank'?10000:(int)$g['total_rounds']) || !$quantity || $quantity<1 || $quantity>500 || ($rule==='rank' && $quantity!==1)) throw new InvalidArgumentException('Revisa los datos: objetivo alcanzable, cantidad de 1 a 500; clasificación entrega una unidad al puesto elegido');
                $db->prepare('UPDATE game_prizes SET title=?,products=?,rule=?,target=?,quantity=?,enabled=? WHERE id=? AND game_id=?')->execute([$title,$products,$rule,$target,$quantity,($data['enabled']??'0')==='1'?1:0,(int)($data['prize_id']??0),$id]);
            }
            $db->commit();return ['success'=>true];
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function state(int $id): array {
        $db=Database::getInstance()->pdo();$db->beginTransaction();
        try {
            // Same lock as game transitions: stock cannot be awarded twice by simultaneous polls.
            $s=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$s->execute([$id]);$g=$s->fetch();
            self::defaults($db,$g);
            $s=$db->prepare('SELECT * FROM game_prizes WHERE game_id=? ORDER BY slot');$s->execute([$id]);$prizes=$s->fetchAll();
            if ($g['session_kind']!=='tutorial' && $prizes && in_array($g['status'],['results','finished'],true) && (int)$g['current_round']>0) {
                $check=$db->prepare('INSERT IGNORE INTO game_prize_checks(game_id,phase) VALUES (?,?)');$check->execute([$id,$g['status'].':'.$g['current_round']]);
                if($check->rowCount())self::award($db,$g,$prizes);
            }
            $s=$db->prepare('SELECT a.*,p.name AS player_name FROM game_awards a JOIN players p ON p.id=a.player_id WHERE a.game_id=? ORDER BY a.id');$s->execute([$id]);$awards=$s->fetchAll();
            $db->commit();return ['items'=>$prizes,'awards'=>$awards];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    private static function award(PDO $db,array $g,array $prizes): void {
        $id=(int)$g['id'];$m=($g['game_type']??'song')==='meme';$answers=$m?'meme_answers':'answers';$items=$m?'game_memes':'game_songs';$col=$m?'meme_id':'song_id';
        $s=$db->prepare("SELECT a.id,a.player_id,a.is_correct,a.points_earned,i.round_number FROM $answers a JOIN $items i ON i.game_id=a.game_id AND i.$col=a.$col JOIN players p ON p.id=a.player_id WHERE a.game_id=? AND p.is_bot=0 AND i.round_number<=? ORDER BY i.round_number,a.id");
        $s->execute([$id,$g['current_round']]);$answers=$s->fetchAll();$stats=[];$candidates=[];
        foreach($answers as $a){
            $p=(int)$a['player_id'];$r=(int)$a['round_number'];$v=$stats[$p]??['correct'=>0,'streak'=>0,'round'=>0,'score'=>0,'last'=>0];
            $ok=(bool)$a['is_correct'];$v['streak']=$ok?($v['round']===$r-1?$v['streak']+1:1):0;$v['correct']+=(int)$ok;$v['round']=$r;$v['score']+=(int)$a['points_earned'];if($ok)$v['last']=(int)$a['id'];$stats[$p]=$v;
            foreach($prizes as $pr) if($pr['enabled'] && $pr['rule']!=='rank' && $v[$pr['rule']] >= (int)$pr['target'] && !isset($candidates[$pr['id']][$p]))$candidates[$pr['id']][$p]=$r;
        }
        // Final ranking excludes bots; equal scores: more correct, then earlier last scoring answer.
        uksort($stats,static function($a,$b)use($stats){$x=$stats[$a];$y=$stats[$b];return ($y['score']<=>$x['score'])?:($y['correct']<=>$x['correct'])?:($x['last']<=>$y['last'])?:($a<=>$b);});
        foreach($prizes as $pr){
            if(!$pr['enabled'])continue;
            $s=$db->prepare('SELECT player_id FROM game_awards WHERE prize_id=?');$s->execute([$pr['id']]);$won=$s->fetchAll(PDO::FETCH_COLUMN);$remaining=(int)$pr['quantity']-count($won);
            $eligible=$candidates[$pr['id']]??[];
            if($pr['rule']==='rank' && $g['status']==='finished' && (int)$g['current_round'] >= (int)$g['total_rounds']){
                $p=array_keys($stats)[(int)$pr['target']-1]??null;$eligible=$p!==null?[$p=>(int)$g['total_rounds']]:[];
            }
            foreach($eligible as $p=>$round){if($remaining<=0)break;if(in_array($p,$won))continue;$db->prepare('INSERT INTO game_awards(game_id,prize_id,player_id,round_number) VALUES (?,?,?,?)')->execute([$id,$pr['id'],$p,$round]);$remaining--;}
        }
    }
}
