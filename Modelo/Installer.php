<?php
class Installer {

    public static function run(string $host, string $user, string $pass, string $dbName): void {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // ── Versión de esquema ───────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (version INT DEFAULT 0)");
        $row = $pdo->query("SELECT version FROM schema_version LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $currentVersion = $row ? (int)$row['version'] : 0;

        if ($currentVersion >= 2) return; // Ya instalado

        // Borrar tablas antiguas para garantizar esquema limpio
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['answers','player_timeline','game_songs','players','songs','games','schema_version'] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // ── Tablas ──────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE games (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                pin                 CHAR(4) NOT NULL,
                admin_token         VARCHAR(64) NOT NULL,
                status              ENUM('waiting','question','results','finished') DEFAULT 'waiting',
                current_round       INT DEFAULT 0,
                total_rounds        INT DEFAULT 10,
                question_time       INT DEFAULT 30,
                question_started_at TIMESTAMP NULL,
                created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pin (pin)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE songs (
                id     INT AUTO_INCREMENT PRIMARY KEY,
                title  VARCHAR(200) NOT NULL,
                artist VARCHAR(200) NOT NULL,
                year   INT NOT NULL,
                genre  VARCHAR(100)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE players (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                game_id      INT NOT NULL,
                name         VARCHAR(50) NOT NULL,
                score        INT DEFAULT 0,
                avatar_color VARCHAR(7) DEFAULT '#FF6B6B',
                last_seen    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE game_songs (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                game_id      INT NOT NULL,
                song_id      INT NOT NULL,
                round_number INT NOT NULL,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE player_timeline (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                game_id   INT NOT NULL,
                song_id   INT NOT NULL,
                UNIQUE KEY unique_entry (player_id, game_id, song_id),
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
                FOREIGN KEY (song_id)   REFERENCES songs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE answers (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                game_id        INT NOT NULL,
                player_id      INT NOT NULL,
                song_id        INT NOT NULL,
                position_guess INT NOT NULL DEFAULT 0,
                is_correct     TINYINT(1) DEFAULT 0,
                points_earned  INT DEFAULT 0,
                answered_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_answer (game_id, player_id, song_id),
                FOREIGN KEY (game_id)   REFERENCES games(id),
                FOREIGN KEY (player_id) REFERENCES players(id),
                FOREIGN KEY (song_id)   REFERENCES songs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("CREATE TABLE schema_version (version INT DEFAULT 0)");
        $pdo->exec("INSERT INTO schema_version (version) VALUES (2)");

        // ── Canciones de muestra ───────────────────────────
        $ins = $pdo->prepare("INSERT INTO songs (title, artist, year, genre) VALUES (?,?,?,?)");
        foreach (self::songs() as $s) { $ins->execute($s); }
    }

    private static function songs(): array {
        return [
            ['Rock Around the Clock',        'Bill Haley & His Comets', 1954, 'Rock and Roll'],
            ['Johnny B. Goode',              'Chuck Berry',             1958, 'Rock and Roll'],
            ['La Bamba',                     'Ritchie Valens',          1958, 'Rock and Roll'],
            ['House of the Rising Sun',      'The Animals',             1964, 'Rock'],
            ['Yesterday',                    'The Beatles',             1965, 'Pop'],
            ['Purple Haze',                  'Jimi Hendrix',            1967, 'Rock'],
            ['Hey Jude',                     'The Beatles',             1968, 'Pop Rock'],
            ['Let It Be',                    'The Beatles',             1970, 'Pop Rock'],
            ['Stairway to Heaven',           'Led Zeppelin',            1971, 'Rock'],
            ['Bohemian Rhapsody',            'Queen',                   1975, 'Rock'],
            ['Hotel California',             'Eagles',                  1977, 'Rock'],
            ['Another Brick in the Wall',    'Pink Floyd',              1979, 'Rock'],
            ["Don't Stop Believin'",         'Journey',                 1981, 'Rock'],
            ['Thriller',                     'Michael Jackson',         1982, 'Pop'],
            ['Every Breath You Take',        'The Police',              1983, 'Pop'],
            ['Girls Just Want to Have Fun',  'Cyndi Lauper',            1983, 'Pop'],
            ['Take On Me',                   'a-ha',                    1985, 'Synth-pop'],
            ['With or Without You',          'U2',                      1987, 'Rock'],
            ['Like a Prayer',                'Madonna',                 1989, 'Pop'],
            ['Nothing Compares 2 U',         "Sinéad O'Connor",         1990, 'Pop'],
            ['Smells Like Teen Spirit',      'Nirvana',                 1991, 'Rock'],
            ['Losing My Religion',           'R.E.M.',                  1991, 'Alternative'],
            ['Wonderwall',                   'Oasis',                   1995, 'Britpop'],
            ['Killing Me Softly',            'Fugees',                  1996, 'Hip-Hop'],
            ['Bitter Sweet Symphony',        'The Verve',               1997, 'Alternative'],
            ['...Baby One More Time',        'Britney Spears',          1998, 'Pop'],
            ['No Scrubs',                    'TLC',                     1999, 'R&B'],
            ['Beautiful Day',                'U2',                      2000, 'Rock'],
            ['Crazy in Love',                'Beyoncé',                 2003, 'R&B'],
            ['Hey Ya!',                      'OutKast',                 2003, 'Hip-Hop'],
            ['Since U Been Gone',            'Kelly Clarkson',          2004, 'Pop Rock'],
            ['Umbrella',                     'Rihanna',                 2007, 'Pop'],
            ['Rolling in the Deep',          'Adele',                   2010, 'Pop'],
            ['Somebody That I Used to Know', 'Gotye',                   2011, 'Indie Pop'],
            ['Blurred Lines',                'Robin Thicke',            2013, 'Pop'],
            ['Happy',                        'Pharrell Williams',        2013, 'Pop'],
            ['Shape of You',                 'Ed Sheeran',              2017, 'Pop'],
            ['God\'s Plan',                  'Drake',                   2018, 'Hip-Hop'],
            ['Old Town Road',                'Lil Nas X',               2019, 'Country Rap'],
            ['Drivers License',              'Olivia Rodrigo',          2021, 'Pop'],
        ];
    }
}
