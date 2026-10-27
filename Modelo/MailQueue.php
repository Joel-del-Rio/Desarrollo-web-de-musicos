<?php
require_once __DIR__.'/EmailService.php';
final class MailQueue {
    public static function enqueue(PDO $db,int $gameId,?int $inviteId,string $email,string $pin,string $kind='player'): void {
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo no válido');
        // Suppress double taps/reloads and rapid duplicate resends.
        $st=$db->prepare("SELECT id FROM mail_queue WHERE game_id=? AND invitation_id <=> ? AND recipient=? AND status IN ('queued','retry','sending','sent') AND created_at>UTC_TIMESTAMP()-INTERVAL 2 MINUTE");
        $st->execute([$gameId,$inviteId,$email]); if ($st->fetch()) return;
        $db->prepare("INSERT INTO mail_queue (game_id,invitation_id,recipient,pin,kind,available_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())")->execute([$gameId,$inviteId,$email,$pin,$kind]);
        self::kick();
    }
    public static function kick(): void {
        static $scheduled=false;
        if ($scheduled || PHP_OS_FAMILY==='Windows' || PHP_SAPI==='cli') return;
        $scheduled=true;
        register_shutdown_function(static function() {
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            if (function_exists('exec')) @exec('nohup /usr/local/bin/php '.escapeshellarg(__DIR__.'/../Cron/mail_worker.php').' > /dev/null 2>&1 &');
        });
    }
    public static function run(PDO $db): array {
        if (!(int)$db->query("SELECT GET_LOCK('hitstoric_mail_worker',0)")->fetchColumn()) return ['busy'=>true];
        $sent=0;$failed=0;$started=microtime(true);
        try {
            // A crashed delivery is ambiguous; never resend it automatically.
            $db->exec("UPDATE mail_queue SET status='unknown' WHERE status='sending' AND available_at<UTC_TIMESTAMP()-INTERVAL 10 MINUTE");
            while (microtime(true)-$started<45 && $sent+$failed<100) {
                $row=$db->query("SELECT * FROM mail_queue WHERE status IN ('queued','retry') AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if (!$row) break;
                $db->beginTransaction();
                $st=$db->prepare('SELECT status FROM games WHERE id=? FOR UPDATE');$st->execute([$row['game_id']]);
                if ($st->fetchColumn()!=='waiting') {
                    $db->prepare("UPDATE mail_queue SET status='cancelled' WHERE id=?")->execute([$row['id']]);$db->commit();continue;
                }
                $st=$db->prepare("UPDATE mail_queue SET status='sending',attempts=attempts+1,available_at=UTC_TIMESTAMP() WHERE id=? AND status IN ('queued','retry')");$st->execute([$row['id']]);
                $claimed=$st->rowCount();$db->commit();if (!$claimed) continue;
                try {
                    $ok=$row['kind']==='organizer' ? EmailService::sendGameCreated($row['recipient'],$row['pin'],BASE_URL,'shared') : EmailService::sendPlayerPin($row['recipient'],$row['pin'],BASE_URL,(int)$row['invitation_id'],(int)$row['game_id']);
                } catch (Throwable $e) { $ok=false; }
                $status=$ok?'sent':(((int)$row['attempts']+1)>=3?'failed':'retry');
                $db->prepare("UPDATE mail_queue SET status=?,sent_at=IF(?='sent',UTC_TIMESTAMP(),NULL),available_at=UTC_TIMESTAMP()+INTERVAL 5 MINUTE WHERE id=?")->execute([$status,$status,$row['id']]);
                $ok?$sent++:$failed++;
            }
        } finally { if ($db->inTransaction()) $db->rollBack(); $db->query("SELECT RELEASE_LOCK('hitstoric_mail_worker')"); }
        return ['sent'=>$sent,'failed'=>$failed];
    }
}
