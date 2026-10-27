<?php
final class PlanningSchema {
    public static function migrate(PDO $db): void {
        // Additive, retryable migration: existing games and scores remain intact.
        foreach (['games' => ['host_key_hash' => 'CHAR(64) NULL', 'title' => "VARCHAR(120) NOT NULL DEFAULT ''", 'planned_at' => 'DATETIME NULL', 'prize_1'=>'VARCHAR(200) NULL', 'prize_2'=>'VARCHAR(200) NULL', 'prize_3'=>'VARCHAR(200) NULL'],
                  'individual_pins' => ['display_name' => "VARCHAR(30) NOT NULL DEFAULT ''", 'revoked' => 'TINYINT(1) NOT NULL DEFAULT 0']] as $table => $columns) {
            $existing = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns as $name => $type) {
                if (!in_array($name, $existing, true)) $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $type");
            }
        }
        $db->exec('ALTER TABLE games MODIFY pin VARCHAR(8) NOT NULL');
        $db->exec('ALTER TABLE individual_pins MODIFY pin VARCHAR(8) NOT NULL');
        foreach (['games'=>['idx_host_key'=>'host_key_hash'],'individual_pins'=>['idx_invitation_pin'=>'pin']] as $table=>$indexes) {
            $existing=array_column($db->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),'Key_name');
            foreach($indexes as $name=>$column) if(!in_array($name,$existing,true))$db->exec("ALTER TABLE `$table` ADD INDEX `$name` (`$column`)");
        }
        $db->exec("CREATE TABLE IF NOT EXISTS mail_queue (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, game_id INT NOT NULL, invitation_id INT NULL,
            recipient VARCHAR(255) NOT NULL, pin VARCHAR(8) NOT NULL, kind VARCHAR(20) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL, sent_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX pending_mail (status, available_at), INDEX game_mail (game_id, invitation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('UPDATE schema_version SET version=28');
    }
}
