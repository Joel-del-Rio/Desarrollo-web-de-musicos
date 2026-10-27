<?php
require_once __DIR__.'/Database.php';
final class SongCatalog {
    public static function migrate(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS song_validation (song_id INT PRIMARY KEY, status VARCHAR(24) NOT NULL DEFAULT 'pending', title VARCHAR(255) NOT NULL, artist VARCHAR(255) NOT NULL, year INT NOT NULL, preview_url TEXT NULL, source_url TEXT NULL, source_track_id BIGINT NULL, reason TEXT NULL, checked_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('UPDATE schema_version SET version=32');
    }
    public static function source(): string {
        return "(SELECT s.* FROM songs s JOIN song_validation v ON v.song_id=s.id AND v.status='verified' AND BINARY v.title=BINARY s.title AND BINARY v.artist=BINARY s.artist AND v.year=s.year AND v.preview_url IS NOT NULL) AS approved_songs";
    }
    public static function preview(int $id): array {
        $db=Database::getInstance()->pdo();
        $q=$db->prepare("SELECT v.preview_url,v.source_url,s.artwork_url FROM songs s JOIN song_validation v ON v.song_id=s.id WHERE s.id=? AND v.status='verified' AND BINARY v.title=BINARY s.title AND BINARY v.artist=BINARY s.artist AND v.year=s.year");$q->execute([$id]);$row=$q->fetch();
        return ['previewUrl'=>$row['preview_url']??null,'artworkUrl'=>$row['artwork_url']??null,'verified'=>(bool)$row];
    }
}
