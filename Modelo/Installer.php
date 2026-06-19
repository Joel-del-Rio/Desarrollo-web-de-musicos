<?php
/**
 * Installer.php — Instalador y sistema de migraciones de la BD
 *
 * Se ejecuta una sola vez al iniciar Database (vía run()).
 * Detecta la versión actual del esquema en schema_version y aplica
 * todas las migraciones pendientes de forma incremental.
 * En instalaciones nuevas (versión 0), crea todas las tablas desde cero.
 */
class Installer {

    /**
     * Punto de entrada principal. Recibe las credenciales de conexión,
     * crea la BD si no existe, detecta la versión del esquema y migra.
     */
    public static function run(string $host, string $user, string $pass, string $dbName): void {
        // Conectar sin seleccionar BD para poder crearla si no existe
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // Leer versión actual; si la tabla no existe aún, la versión es 0
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (version INT DEFAULT 0)");
        $row = $pdo->query("SELECT version FROM schema_version LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $currentVersion = $row ? (int)$row['version'] : 0;

        // ── Migraciones incrementales ─────────────────────────────

        // v4: columnas de streaming (Spotify/YouTube y opciones de partida)
        if ($currentVersion === 3) {
            try { $pdo->exec("ALTER TABLE songs ADD COLUMN spotify_url VARCHAR(500) NULL, ADD COLUMN youtube_url VARCHAR(500) NULL"); } catch (\Exception $e) {}
            try { $pdo->exec("ALTER TABLE games ADD COLUMN show_links TINYINT(1) DEFAULT 0, ADD COLUMN embed_youtube TINYINT(1) DEFAULT 0, ADD COLUMN autoplay TINYINT(1) DEFAULT 0"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=4");
            $currentVersion = 4;
        }

        // v5: modo PIN individual, email del organizador, tabla individual_pins
        if ($currentVersion === 4) {
            try { $pdo->exec("ALTER TABLE games ADD COLUMN pin_mode ENUM('shared','individual') DEFAULT 'shared'"); } catch (\Exception $e) {}
            try { $pdo->exec("ALTER TABLE games ADD COLUMN organizer_email VARCHAR(255) NULL"); } catch (\Exception $e) {}
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS individual_pins (
                        id         INT AUTO_INCREMENT PRIMARY KEY,
                        game_id    INT NOT NULL,
                        pin        CHAR(4) NOT NULL,
                        used       TINYINT(1) DEFAULT 0,
                        player_id  INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_pin_per_game (game_id, pin),
                        FOREIGN KEY (game_id)   REFERENCES games(id) ON DELETE CASCADE,
                        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=5");
            return;
        }

        // v6: premios por partida (1er, 2º y 3er puesto)
        if ($currentVersion === 5) {
            try { $pdo->exec("ALTER TABLE games ADD COLUMN prize_1 VARCHAR(200) NULL, ADD COLUMN prize_2 VARCHAR(200) NULL, ADD COLUMN prize_3 VARCHAR(200) NULL"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=6");
            $currentVersion = 6;
        }

        // v7: email en jugadores, tabla global_scores y catálogo de premios
        if ($currentVersion === 6) {
            try { $pdo->exec("ALTER TABLE players ADD COLUMN email VARCHAR(255) NULL"); } catch (\Exception $e) {}
            try { $pdo->exec("
                CREATE TABLE IF NOT EXISTS global_scores (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    email        VARCHAR(255) NOT NULL,
                    name         VARCHAR(50)  NOT NULL,
                    total_points INT DEFAULT 0,
                    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            "); } catch (\Exception $e) {}
            try { $pdo->exec("
                CREATE TABLE IF NOT EXISTS prizes_catalog (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    name        VARCHAR(200) NOT NULL,
                    description VARCHAR(500) NULL,
                    points_cost INT NOT NULL DEFAULT 1000,
                    stock       INT DEFAULT -1,
                    active      TINYINT(1) DEFAULT 1,
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            "); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=7");
            $currentVersion = 7;
        }

        // v8: columna emoji en prizes_catalog (luego renombrada a image en v9)
        if ($currentVersion === 7) {
            try { $pdo->exec("ALTER TABLE prizes_catalog ADD COLUMN emoji VARCHAR(16) NULL AFTER description"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=8");
            $currentVersion = 8;
        }

        // v9: renombrar emoji → image y ampliar tamaño para guardar nombre de archivo
        if ($currentVersion === 8) {
            try { $pdo->exec("ALTER TABLE prizes_catalog CHANGE COLUMN emoji image VARCHAR(255) NULL"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=9");
            $currentVersion = 9;
        }

        // v10: guardar email del jugador en individual_pins para recuperarlo al hacer join
        if ($currentVersion === 9) {
            try { $pdo->exec("ALTER TABLE individual_pins ADD COLUMN email VARCHAR(255) NULL"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=10");
            $currentVersion = 10;
        }

        // v11: añadir columnas de posición y puntuación a answers
        // (ausentes en instalaciones antiguas que crearon answers sin estas columnas)
        if ($currentVersion === 10) {
            try { $pdo->exec("ALTER TABLE answers ADD COLUMN position_guess INT NOT NULL DEFAULT 0"); } catch (\Exception $e) {}
            try { $pdo->exec("ALTER TABLE answers ADD COLUMN is_correct TINYINT(1) DEFAULT 0");      } catch (\Exception $e) {}
            try { $pdo->exec("ALTER TABLE answers ADD COLUMN points_earned INT DEFAULT 0");           } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=11");
            $currentVersion = 11;
        }

        // v12: columna de racha para el sistema de multiplicador de puntos por aciertos consecutivos
        if ($currentVersion === 11) {
            try { $pdo->exec("ALTER TABLE players ADD COLUMN streak INT DEFAULT 0"); } catch (\Exception $e) {}
            $pdo->exec("UPDATE schema_version SET version=12");
            $currentVersion = 12;
        }

        // Esquema actualizado: verificar integridad y salir
        if ($currentVersion >= 12) {
            // Garantizar que individual_pins existe aunque la migración v5 fallara parcialmente
            $tables = $pdo->query("SHOW TABLES LIKE 'individual_pins'")->fetchAll();
            if (empty($tables)) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS individual_pins (
                        id         INT AUTO_INCREMENT PRIMARY KEY,
                        game_id    INT NOT NULL,
                        pin        CHAR(4) NOT NULL,
                        used       TINYINT(1) DEFAULT 0,
                        player_id  INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_pin_per_game (game_id, pin),
                        FOREIGN KEY (game_id)   REFERENCES games(id) ON DELETE CASCADE,
                        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
            return;
        }

        // ── Instalación desde cero (versión 0) ───────────────────

        // Borrar tablas antiguas para garantizar esquema limpio en una reinstalación
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['answers','player_timeline','game_songs','players','songs','games','schema_version'] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Tabla principal de partidas
        $pdo->exec("
            CREATE TABLE games (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                pin                 CHAR(4) NOT NULL,
                admin_token         VARCHAR(64) NOT NULL,
                status              ENUM('waiting','question','results','finished') DEFAULT 'waiting',
                current_round       INT DEFAULT 0,
                total_rounds        INT DEFAULT 10,
                question_time       INT DEFAULT 30,
                selected_genre      VARCHAR(100) DEFAULT 'Todos',
                show_links          TINYINT(1) DEFAULT 0,
                embed_youtube       TINYINT(1) DEFAULT 0,
                autoplay            TINYINT(1) DEFAULT 0,
                pin_mode            ENUM('shared','individual') DEFAULT 'shared',
                organizer_email     VARCHAR(255) NULL,
                question_started_at TIMESTAMP NULL,
                created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pin (pin)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Catálogo de canciones con sus URLs de streaming opcionales
        $pdo->exec("
            CREATE TABLE songs (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                title        VARCHAR(200) NOT NULL,
                artist       VARCHAR(200) NOT NULL,
                year         INT NOT NULL,
                genre        VARCHAR(100),
                spotify_url  VARCHAR(500) NULL,
                youtube_url  VARCHAR(500) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Jugadores de cada partida
        $pdo->exec("
            CREATE TABLE players (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                game_id      INT NOT NULL,
                name         VARCHAR(50) NOT NULL,
                score        INT DEFAULT 0,
                avatar_color VARCHAR(7) DEFAULT '#FF6B6B',
                email        VARCHAR(255) NULL,
                streak       INT DEFAULT 0,
                last_seen    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Relación entre partidas y canciones (con número de ronda)
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

        // Línea del tiempo personal de cada jugador (canciones ya colocadas)
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

        // Respuestas de posición de cada jugador por ronda
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

        // PINs de acceso individual (un PIN único por jugador en el modo individual)
        $pdo->exec("
            CREATE TABLE individual_pins (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                game_id    INT NOT NULL,
                pin        CHAR(4) NOT NULL,
                used       TINYINT(1) DEFAULT 0,
                player_id  INT NULL,
                email      VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pin_per_game (game_id, pin),
                FOREIGN KEY (game_id)   REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Puntuación global acumulada por email (para el ranking de premios)
        $pdo->exec("
            CREATE TABLE global_scores (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                email        VARCHAR(255) NOT NULL,
                name         VARCHAR(50)  NOT NULL,
                total_points INT DEFAULT 0,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Catálogo de premios que se pueden canjear con puntos globales
        $pdo->exec("
            CREATE TABLE prizes_catalog (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(200) NOT NULL,
                description VARCHAR(500) NULL,
                image       VARCHAR(255) NULL,
                points_cost INT NOT NULL DEFAULT 1000,
                stock       INT DEFAULT -1,
                active      TINYINT(1) DEFAULT 1,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("CREATE TABLE schema_version (version INT DEFAULT 0)");
        $pdo->exec("INSERT INTO schema_version (version) VALUES (12)");

        // Insertar las canciones de muestra del catálogo inicial
        $ins = $pdo->prepare("INSERT INTO songs (title, artist, year, genre) VALUES (?,?,?,?)");
        foreach (self::songs() as $s) { $ins->execute($s); }
    }

    /**
     * Catálogo inicial de canciones agrupadas por género.
     * Se inserta solo en instalaciones nuevas (versión 0).
     */
    private static function songs(): array {
        return [
            // Rock Internacional
            ['Stairway to Heaven',         'Led Zeppelin',                1971, 'Rock Internacional'],
            ['Bohemian Rhapsody',          'Queen',                       1975, 'Rock Internacional'],
            ['Hotel California',           'Eagles',                      1977, 'Rock Internacional'],
            ['Back in Black',              'AC/DC',                       1980, 'Rock Internacional'],
            ['Smells Like Teen Spirit',    'Nirvana',                     1991, 'Rock Internacional'],
            ['Sweet Child O Mine',         'Guns N Roses',                1988, 'Rock Internacional'],
            ['Enter Sandman',              'Metallica',                   1991, 'Rock Internacional'],
            ['Wonderwall',                 'Oasis',                       1995, 'Rock Internacional'],
            ['Seven Nation Army',          'The White Stripes',           2003, 'Rock Internacional'],
            ['Livin on a Prayer',          'Bon Jovi',                    1986, 'Rock Internacional'],
            ["Don't Stop Believin'",       'Journey',                     1981, 'Rock Internacional'],
            ['Eye of the Tiger',           'Survivor',                    1982, 'Rock Internacional'],
            ['Jump',                       'Van Halen',                   1984, 'Rock Internacional'],
            ['Pour Some Sugar on Me',      'Def Leppard',                 1987, 'Rock Internacional'],
            ['Under the Bridge',           'Red Hot Chili Peppers',       1992, 'Rock Internacional'],
            ['Black Hole Sun',             'Soundgarden',                 1994, 'Rock Internacional'],
            ['With or Without You',        'U2',                          1987, 'Rock Internacional'],
            ['Creep',                      'Radiohead',                   1992, 'Rock Internacional'],
            ['Mr. Brightside',             'The Killers',                 2003, 'Rock Internacional'],
            ['Last Resort',                'Papa Roach',                  2000, 'Rock Internacional'],
            ['In the End',                 'Linkin Park',                 2000, 'Rock Internacional'],
            // Pop/Rock Español
            ['En el Rio',                  'Vetusta Morla',               2011, 'Pop/Rock Español'],
            ['La Bicicleta',               'Carlos Vives & Shakira',      2016, 'Pop/Rock Español'],
            ['Macarena',                   'Los del Rio',                 1993, 'Pop/Rock Español'],
            ['Hijo de la Luna',            'Mecano',                      1986, 'Pop/Rock Español'],
            ['La Chica de Ayer',           'Nino Bravo',                  1973, 'Pop/Rock Español'],
            ['Un Año de Amor',             'Mecano',                      1988, 'Pop/Rock Español'],
            ['Resistire',                  'Duo Dinamico',                1988, 'Pop/Rock Español'],
            ['Corazon Partio',             'Alejandro Sanz',              1997, 'Pop/Rock Español'],
            ['Vivir',                      'Ricky Martin',                1995, 'Pop/Rock Español'],
            ['La Tortura',                 'Shakira ft. Alejandro Sanz',  2005, 'Pop/Rock Español'],
            ['A quien le importa',         'Alaska y Dinarama',           1986, 'Pop/Rock Español'],
            ['El Mar',                     'La Oreja de Van Gogh',        2000, 'Pop/Rock Español'],
            ['Antes de que cuente diez',   'Fito y Fitipaldis',           2009, 'Pop/Rock Español'],
            ['Me quedo contigo',           'Los Chunguitos',              1981, 'Pop/Rock Español'],
            ['Libre',                      'Nino Bravo',                  1972, 'Pop/Rock Español'],
            ['Mediterraneo',               'Joan Manuel Serrat',          1971, 'Pop/Rock Español'],
            ['Veneno en la piel',          'Radio Futura',                1990, 'Pop/Rock Español'],
            // 80s
            ['Sweet Dreams',               'Eurythmics',                  1983, '80s'],
            ['Thriller',                   'Michael Jackson',             1982, '80s'],
            ['Take On Me',                 'a-ha',                        1985, '80s'],
            ['Girls Just Want to Have Fun','Cyndi Lauper',                1983, '80s'],
            ['Like a Prayer',              'Madonna',                     1989, '80s'],
            ["Don't You Forget About Me",  'Simple Minds',                1985, '80s'],
            ['Every Breath You Take',      'The Police',                  1983, '80s'],
            ['Wake Me Up Before You Go-Go','Wham!',                       1984, '80s'],
            ['Tainted Love',               'Soft Cell',                   1981, '80s'],
            ['Come On Eileen',             'Dexys Midnight Runners',      1982, '80s'],
            ['West End Girls',             'Pet Shop Boys',               1985, '80s'],
            ['True',                       'Spandau Ballet',              1983, '80s'],
            ['Faith',                      'George Michael',              1987, '80s'],
            ['Never Gonna Give You Up',    'Rick Astley',                 1987, '80s'],
            ['Total Eclipse of the Heart', 'Bonnie Tyler',                1983, '80s'],
            ['Africa',                     'Toto',                        1982, '80s'],
            // New Age
            ['Watermark',                  'Enya',                        1988, 'New Age'],
            ['Oxygene',                    'Jean-Michel Jarre',           1976, 'New Age'],
            ['Tubular Bells',              'Mike Oldfield',               1973, 'New Age'],
            ['Orinoco Flow',               'Enya',                        1988, 'New Age'],
            ['Chariots of Fire',           'Vangelis',                    1981, 'New Age'],
            ['Return to Innocence',        'Enigma',                      1994, 'New Age'],
            ['Sadeness Part I',            'Enigma',                      1990, 'New Age'],
            ['May It Be',                  'Enya',                        2001, 'New Age'],
            ['Only Time',                  'Enya',                        2000, 'New Age'],
            ['Porcelain',                  'Moby',                        1999, 'New Age'],
            ['Teardrop',                   'Massive Attack',              1998, 'New Age'],
            ['Sweet Lullaby',              'Deep Forest',                 1992, 'New Age'],
            ['Adiemus',                    'Karl Jenkins',                1995, 'New Age'],
            ['Boadicea',                   'Enya',                        1987, 'New Age'],
            ['Song of the Sea',            'Clannad',                     1982, 'New Age'],
            // Rock en Español
            ['Mueve tus Caderas',          'Los Enanitos Verdes',         1992, 'Rock en Español'],
            ['La Camisa Negra',            'Juanes',                      2004, 'Rock en Español'],
            ['Donde Jugaran los Ninos',    'Mana',                        1992, 'Rock en Español'],
            ['Clavado en un Bar',          'Mana',                        1994, 'Rock en Español'],
            ['Oye mi Amor',                'Mana',                        1992, 'Rock en Español'],
            ['Te Quiero',                  'Hombres G',                   1987, 'Rock en Español'],
            ['Devuelveme a mi Chica',      'Hombres G',                   1985, 'Rock en Español'],
            ['En el Muelle de San Blas',   'Mana',                        1994, 'Rock en Español'],
            ['Persiana Americana',         'Soda Stereo',                 1986, 'Rock en Español'],
            ['De Musica Ligera',           'Soda Stereo',                 1990, 'Rock en Español'],
            ['Cuando Pase el Temblor',     'Soda Stereo',                 1985, 'Rock en Español'],
            ['No Digas Nada',              'La Oreja de Van Gogh',        1998, 'Rock en Español'],
            ['Quiero Ser',                 'La Oreja de Van Gogh',        2003, 'Rock en Español'],
            ['El Universo Sobre Mi',       'Amaral',                      2005, 'Rock en Español'],
            ['La Flaca',                   'Jarabe de Palo',              1996, 'Rock en Español'],
            // Trap/Rap Internacional
            ['Stan',                       'Eminem',                      2000, 'Trap/Rap Internacional'],
            ['Lose Yourself',              'Eminem',                      2002, 'Trap/Rap Internacional'],
            ['HUMBLE.',                    'Kendrick Lamar',              2017, 'Trap/Rap Internacional'],
            ['Sicko Mode',                 'Travis Scott',                2018, 'Trap/Rap Internacional'],
            ["God's Plan",                 'Drake',                       2018, 'Trap/Rap Internacional'],
            ['Rockstar',                   'Post Malone',                 2017, 'Trap/Rap Internacional'],
            ['Old Town Road',              'Lil Nas X',                   2019, 'Trap/Rap Internacional'],
            ['Lucid Dreams',               'Juice WRLD',                  2018, 'Trap/Rap Internacional'],
            ['Bad Guy',                    'Billie Eilish',               2019, 'Trap/Rap Internacional'],
            ['Hotline Bling',              'Drake',                       2015, 'Trap/Rap Internacional'],
            ['Sunflower',                  'Post Malone',                 2018, 'Trap/Rap Internacional'],
            ['Congratulations',            'Post Malone',                 2016, 'Trap/Rap Internacional'],
            ['Savage',                     'Megan Thee Stallion',         2020, 'Trap/Rap Internacional'],
            ['MONTERO',                    'Lil Nas X',                   2021, 'Trap/Rap Internacional'],
            // Trap/Rap en Español
            ['Krippy Kush',                'Bad Bunny & Farruko',         2017, 'Trap/Rap en Español'],
            ['Taki Taki',                  'DJ Snake ft. Ozuna',          2018, 'Trap/Rap en Español'],
            ['MIA',                        'Bad Bunny ft. Drake',         2018, 'Trap/Rap en Español'],
            ['Dakiti',                     'Bad Bunny & Jhay Cortez',     2020, 'Trap/Rap en Español'],
            ['Con Calma',                  'Daddy Yankee & Snow',         2019, 'Trap/Rap en Español'],
            ['Safaera',                    'Bad Bunny',                   2020, 'Trap/Rap en Español'],
            ['China',                      'Anuel AA ft. Bad Bunny',      2019, 'Trap/Rap en Español'],
            ['Callaita',                   'Bad Bunny',                   2019, 'Trap/Rap en Español'],
            ['Tusa',                       'Karol G & Nicki Minaj',       2019, 'Trap/Rap en Español'],
            ['Bichota',                    'Karol G',                     2020, 'Trap/Rap en Español'],
            ['Pa Ti',                      'Jhay Cortez & J. Balvin',     2020, 'Trap/Rap en Español'],
            ['Ojitos Lindos',              'Bad Bunny & Bomba Estereo',   2022, 'Trap/Rap en Español'],
            ['Gatubela',                   'Karol G & Maluma',            2021, 'Trap/Rap en Español'],
            // Actualidad
            ['Levitating',                 'Dua Lipa',                    2020, 'Actualidad'],
            ['Blinding Lights',            'The Weeknd',                  2020, 'Actualidad'],
            ['drivers license',            'Olivia Rodrigo',              2021, 'Actualidad'],
            ['Stay',                       'Kid Laroi & Justin Bieber',   2021, 'Actualidad'],
            ['As It Was',                  'Harry Styles',                2022, 'Actualidad'],
            ['Heat Waves',                 'Glass Animals',               2020, 'Actualidad'],
            ['Anti-Hero',                  'Taylor Swift',                2022, 'Actualidad'],
            ['Flowers',                    'Miley Cyrus',                 2023, 'Actualidad'],
            ['Cruel Summer',               'Taylor Swift',                2023, 'Actualidad'],
            ['Vampire',                    'Olivia Rodrigo',              2023, 'Actualidad'],
            ['Quevedo BZRP Session 52',    'Bizarrap & Quevedo',          2022, 'Actualidad'],
            ['Shakira BZRP Session 53',    'Bizarrap & Shakira',          2023, 'Actualidad'],
            ['TQG',                        'Karol G & Shakira',           2023, 'Actualidad'],
            ['Espresso',                   'Sabrina Carpenter',           2024, 'Actualidad'],
            ['Beautiful Things',           'Benson Boone',                2024, 'Actualidad'],
            ['Die With A Smile',           'Lady Gaga & Bruno Mars',      2024, 'Actualidad'],
        ];
    }
}
