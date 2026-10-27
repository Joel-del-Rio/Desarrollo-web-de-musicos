<?php
require_once __DIR__ . '/Game.php';
require_once __DIR__ . '/MailQueue.php';

final class Planner {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->pdo(); }

    public static function hostHash(string $key): string {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) throw new InvalidArgumentException('Acceso privado no válido');
        return hash('sha256', $key);
    }

    public function attach(int $id, string $token, string $key): void {
        if (!(new Game())->verifyAdmin($id, $token)) throw new InvalidArgumentException('No autorizado');
        $hash = self::hostHash($key);
        $st = $this->db->prepare('UPDATE games SET host_key_hash=? WHERE id=? AND (host_key_hash IS NULL OR host_key_hash=?)');
        $st->execute([$hash,$id,$hash]);
    }

    public function listGames(string $key): array {
        $st=$this->db->prepare("SELECT g.id,g.pin,g.admin_token,g.title,g.planned_at,g.status,g.pin_mode,g.created_at,
            (SELECT COUNT(*) FROM players p WHERE p.game_id=g.id) AS player_count,
            (SELECT COUNT(*) FROM individual_pins i WHERE i.game_id=g.id AND i.revoked=0) AS invited_count
            FROM games g WHERE host_key_hash=? ORDER BY (g.status='finished'),g.created_at DESC");
        $st->execute([self::hostHash($key)]);
        return $st->fetchAll();
    }

    public static function details(array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if (mb_strlen($title)>120) throw new InvalidArgumentException('El nombre admite hasta 120 caracteres');
        $date = trim((string)($data['planned_at'] ?? ''));
        if ($date !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date, new DateTimeZone('Europe/Madrid'));
            if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $date) throw new InvalidArgumentException('Fecha no válida');
            $date=$parsed->format('Y-m-d H:i:00');
        }
        return [$title,$date ?: null];
    }

    public function saveDetails(int $id, array $data): void {
        [$title,$date]=self::details($data);
        $this->db->prepare("UPDATE games SET title=?,planned_at=? WHERE id=? AND status='waiting'")->execute([$title,$date,$id]);
    }

    public function invitations(int $id): array {
        $st=$this->db->prepare("SELECT i.id,i.pin,i.display_name,i.email,i.used,i.player_id,
            (SELECT q.status FROM mail_queue q WHERE q.invitation_id=i.id ORDER BY q.id DESC LIMIT 1) AS mail_status
            FROM individual_pins i WHERE i.game_id=? AND i.revoked=0 ORDER BY i.id");
        $st->execute([$id]); return $st->fetchAll();
    }

    public function waiting(int $id, string $token, callable $work): array {
        $this->db->beginTransaction();
        try {
            $st=$this->db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE'); $st->execute([$id]); $game=$st->fetch();
            if (!$game || !hash_equals($game['admin_token'],$token)) throw new InvalidArgumentException('No autorizado');
            if ($game['status']!=='waiting') throw new InvalidArgumentException('Solo se puede editar antes de empezar');
            $result=$work($game);
            $this->db->commit(); return $result;
        } catch (Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function manage(int $id, string $token, string $operation, array $data): array {
        $allocate = $operation==='add';
        if ($allocate && !(int)$this->db->query("SELECT GET_LOCK('hitstoric_pin_allocator',10)")->fetchColumn()) throw new InvalidArgumentException('Servidor ocupado. Inténtalo de nuevo');
        try {
        return $this->waiting($id,$token,function($game) use ($id,$operation,$data) {
            if ($operation==='details') { $this->saveDetails($id,$data); return ['success'=>true]; }
            if ($operation==='cancel') {
                $this->db->prepare("UPDATE games SET status='finished' WHERE id=?")->execute([$id]);
                $this->db->prepare("UPDATE mail_queue SET status='cancelled' WHERE game_id=? AND status IN ('queued','retry')")->execute([$id]);
                return ['success'=>true];
            }
            if ($operation==='remove_player') {
                $pid=(int)($data['player_id']??0);
                $this->db->prepare("UPDATE mail_queue SET status='cancelled' WHERE invitation_id IN (SELECT id FROM individual_pins WHERE player_id=? AND game_id=?) AND status IN ('queued','retry')")->execute([$pid,$id]);
                $this->db->prepare('UPDATE individual_pins SET revoked=1 WHERE player_id=? AND game_id=?')->execute([$pid,$id]);
                if (!(new Player())->remove($pid,$id)) throw new InvalidArgumentException('Jugador no encontrado');
                return ['success'=>true];
            }
            if ($operation==='player') {
                $name=trim((string)($data['name']??''));
                if ($name==='' || mb_strlen($name)>30) throw new InvalidArgumentException('Nombre obligatorio, máximo 30 caracteres');
                $this->db->prepare('UPDATE players SET name=? WHERE id=? AND game_id=?')->execute([$name,(int)($data['player_id']??0),$id]);
                return ['success'=>true];
            }
            if ($game['pin_mode']!=='individual') throw new InvalidArgumentException('Esta sala usa un PIN compartido');
            if ($operation==='add') {
                $count=filter_var($data['count']??1,FILTER_VALIDATE_INT);
                if ($count===false || $count<1 || $count>500) throw new InvalidArgumentException('Añade de 1 a 500 códigos por lote; puedes añadir más lotes');
                $name=trim((string)($data['name']??'')); $email=trim((string)($data['email']??''));
                if (mb_strlen($name)>30 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Revisa el nombre y el correo');
                if ($count>1 && ($name!=='' || $email!=='')) throw new InvalidArgumentException('Para asignar nombre o correo, añade un jugador cada vez');
                $pins=(new Game())->generateIndividualPins($id,$count,$email!==''?[$email]:[]);
                if ($name!=='') $this->db->prepare('UPDATE individual_pins SET display_name=? WHERE game_id=? AND pin=?')->execute([$name,$id,$pins[0]]);
                if ($email!=='' && ($data['send_email']??'0')==='1') {
                    $st=$this->db->prepare('SELECT id FROM individual_pins WHERE game_id=? AND pin=?');$st->execute([$id,$pins[0]]);
                    MailQueue::enqueue($this->db,$id,(int)$st->fetchColumn(),$email,$pins[0]);
                }
                return ['success'=>true,'count'=>count($pins)];
            }
            $st=$this->db->prepare('SELECT * FROM individual_pins WHERE id=? AND game_id=? AND revoked=0 FOR UPDATE');
            $st->execute([(int)($data['invitation_id']??0),$id]);$invite=$st->fetch();
            if (!$invite) throw new InvalidArgumentException('Invitación no encontrada');
            if ($operation==='remove' || $operation==='revoke') {
                if ($operation==='revoke' && $invite['used']) throw new InvalidArgumentException('El jugador ya está dentro; puedes editarlo o expulsarlo');
                $this->db->prepare('UPDATE individual_pins SET revoked=1 WHERE id=?')->execute([$invite['id']]);
                $this->db->prepare("UPDATE mail_queue SET status='cancelled' WHERE invitation_id=? AND status IN ('queued','retry')")->execute([$invite['id']]);
                if ($operation==='remove' && $invite['player_id']) (new Player())->remove((int)$invite['player_id'],$id);
            } elseif ($operation==='edit') {
                $name=trim((string)($data['name']??''));$email=trim((string)($data['email']??''));
                if (mb_strlen($name)>30 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Revisa el nombre y el correo');
                $this->db->prepare('UPDATE individual_pins SET display_name=?,email=? WHERE id=?')->execute([$name,$email?:null,$invite['id']]);
                // An old queued message must not be sent to the previous address.
                if ($email!==($invite['email']??'')) $this->db->prepare("UPDATE mail_queue SET status='cancelled' WHERE invitation_id=? AND status IN ('queued','retry')")->execute([$invite['id']]);
                if ($invite['player_id']) $this->db->prepare("UPDATE players SET email=?,name=IF(?='',name,?) WHERE id=? AND game_id=?")->execute([$email?:null,$name,$name,$invite['player_id'],$id]);
            } elseif ($operation==='send') {
                if (!$invite['email']) throw new InvalidArgumentException('Añade un correo antes de enviar');
                MailQueue::enqueue($this->db,$id,(int)$invite['id'],$invite['email'],$invite['pin']);
            } else throw new InvalidArgumentException('Operación no válida');
            return ['success'=>true];
        });
        } finally { if ($allocate) $this->db->query("SELECT RELEASE_LOCK('hitstoric_pin_allocator')"); }
    }
}
