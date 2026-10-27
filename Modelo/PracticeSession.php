<?php
/** Tutorial rounds share the event, players and admission codes. Only practice data is reset. */
final class PracticeSession {
    public static function migrate(PDO $db): void {
        $columns = ['session_kind'=>"VARCHAR(12) NOT NULL DEFAULT 'main'", 'session_number'=>'INT NOT NULL DEFAULT 0', 'tutorial_count'=>'INT NOT NULL DEFAULT 0', 'competitive_rounds'=>'INT NULL', 'main_started'=>'TINYINT NOT NULL DEFAULT 0'];
        $existing = $db->query('SHOW COLUMNS FROM games')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $name=>$type) if (!in_array($name,$existing,true)) $db->exec("ALTER TABLE games ADD COLUMN $name $type");
        // Existing active/finished events must never be reset as a tutorial.
        $db->exec("UPDATE games SET main_started=1 WHERE status<>'waiting' AND session_kind='main'");
        $db->exec('UPDATE schema_version SET version=31');
    }

    public static function migrateHistory(PDO $db): void {
        $db->exec('CREATE TABLE IF NOT EXISTS tutorial_song_history (game_id INT NOT NULL, song_id INT NOT NULL, PRIMARY KEY(game_id,song_id)) ENGINE=InnoDB');
        $db->exec('UPDATE schema_version SET version=33');
    }

    public static function checkSession(array $game, array $request): void {
        if ((int)$game['session_number']>0 && (int)($request['expected_session']??-1)!==(int)$game['session_number']) {
            throw new InvalidArgumentException('Ha cambiado el tutorial o la partida. Actualiza la pantalla.');
        }
    }

    /** Caller holds the event row lock and transaction. */
    public static function prepare(PDO $db, array $game, string $kind): void {
        if (!in_array($kind,['tutorial','main'],true)) throw new InvalidArgumentException('Modo no válido');
        if ($game['status']!=='waiting' || $game['main_started']) throw new InvalidArgumentException('La partida con premios solo se puede iniciar una vez');
        if ($kind==='tutorial' && $game['game_type']!=='song') throw new InvalidArgumentException('El tutorial es de cinco canciones');
        $id=(int)$game['id'];
        // Preserve every practice song and revealed reference before resetting practice data.
        if ($game['session_kind']==='tutorial' && $game['game_type']==='song') {
            $db->prepare('INSERT IGNORE INTO tutorial_song_history (game_id,song_id) SELECT game_id,song_id FROM game_songs WHERE game_id=?')->execute([$id]);
            $db->prepare('INSERT IGNORE INTO tutorial_song_history (game_id,song_id) SELECT game_id,song_id FROM player_timeline WHERE game_id=?')->execute([$id]);
        }
        // No real competition data can reach this branch.
        if ($game['session_kind']==='tutorial') {
            foreach (['answers','meme_answers','player_timeline','player_meme_timeline','reactions'] as $table) {
                $db->prepare("DELETE FROM $table WHERE game_id=?")->execute([$id]);
            }
            $db->prepare('UPDATE players SET score=0,streak=0 WHERE game_id=?')->execute([$id]);
        }
        $rounds=(int)($game['competitive_rounds']??$game['total_rounds']);
        $db->prepare('UPDATE games SET session_kind=?,session_number=session_number+1,tutorial_count=tutorial_count+?,competitive_rounds=?,total_rounds=?,main_started=?,current_round=0 WHERE id=?')
            ->execute([$kind,$kind==='tutorial'?1:0,$rounds,$kind==='tutorial'?5:$rounds,$kind==='main'?1:0,$id]);
    }
}
