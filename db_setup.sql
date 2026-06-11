-- ============================================================
--  Hitstoric — Esquema completo (versión 5)
--  Última actualización: 2026-06-11
-- ============================================================

CREATE DATABASE IF NOT EXISTS hitster_musicos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE hitster_musicos;

-- ── Control de versión del esquema ──────────────────────────
CREATE TABLE IF NOT EXISTS schema_version (
    version INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_version (version)
SELECT 5 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM schema_version);

-- ── Partidas ────────────────────────────────────────────────
-- embed_youtube se usa como "audio habilitado" (1 = sí)
CREATE TABLE IF NOT EXISTS games (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    pin                 CHAR(4) NOT NULL,
    admin_token         VARCHAR(64) NOT NULL,
    status              ENUM('waiting','question','results','finished') DEFAULT 'waiting',
    current_round       INT DEFAULT 0,
    total_rounds        INT DEFAULT 10,
    question_time       INT DEFAULT 30,
    selected_genre      VARCHAR(100) DEFAULT 'Todos',
    show_links          TINYINT(1) DEFAULT 0,   -- mostrar botones Spotify/YouTube
    embed_youtube       TINYINT(1) DEFAULT 0,   -- reproductor de audio activo
    autoplay            TINYINT(1) DEFAULT 0,   -- autoplay al cargar audio
    pin_mode            ENUM('shared','individual') DEFAULT 'shared',
    organizer_email     VARCHAR(255) NULL,
    question_started_at TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pin (pin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Canciones del catálogo ───────────────────────────────────
CREATE TABLE IF NOT EXISTS songs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    artist      VARCHAR(200) NOT NULL,
    year        INT NOT NULL,
    genre       VARCHAR(100),
    spotify_url VARCHAR(500) NULL,   -- reservado (no usado actualmente)
    youtube_url VARCHAR(500) NULL,   -- reservado (no usado actualmente)
    UNIQUE KEY unique_song (title, artist)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Jugadores ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS players (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    name         VARCHAR(50) NOT NULL,
    score        INT DEFAULT 0,
    avatar_color VARCHAR(7) DEFAULT '#FF6B6B',
    last_seen    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Canciones asignadas a cada partida (orden de rondas) ─────
CREATE TABLE IF NOT EXISTS game_songs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    song_id      INT NOT NULL,
    round_number INT NOT NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Línea del tiempo de cada jugador (canciones ya colocadas) ─
CREATE TABLE IF NOT EXISTS player_timeline (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    game_id   INT NOT NULL,
    song_id   INT NOT NULL,
    UNIQUE KEY unique_entry (player_id, game_id, song_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
    FOREIGN KEY (song_id)   REFERENCES songs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Respuestas de jugadores por ronda ────────────────────────
CREATE TABLE IF NOT EXISTS answers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    game_id        INT NOT NULL,
    player_id      INT NOT NULL,
    song_id        INT NOT NULL,
    position_guess INT NOT NULL DEFAULT 0,  -- posición elegida en el timeline
    is_correct     TINYINT(1) DEFAULT 0,
    points_earned  INT DEFAULT 0,
    answered_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (game_id, player_id, song_id),
    FOREIGN KEY (game_id)   REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (song_id)   REFERENCES songs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PINs individuales (modo de acceso individual) ────────────
CREATE TABLE IF NOT EXISTS individual_pins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    game_id    INT NOT NULL,
    pin        CHAR(4) NOT NULL,
    used       TINYINT(1) DEFAULT 0,
    player_id  INT NULL,               -- NULL hasta que el jugador se une
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pin_per_game (game_id, pin),
    FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Canciones de muestra (8 géneros, ~160 canciones)
-- ============================================================
INSERT IGNORE INTO songs (title, artist, year, genre) VALUES

-- Rock Internacional
('Stairway to Heaven',         'Led Zeppelin',                1971, 'Rock Internacional'),
('Bohemian Rhapsody',          'Queen',                       1975, 'Rock Internacional'),
('Hotel California',           'Eagles',                      1977, 'Rock Internacional'),
('Back in Black',              'AC/DC',                       1980, 'Rock Internacional'),
('Smells Like Teen Spirit',    'Nirvana',                     1991, 'Rock Internacional'),
('Sweet Child O Mine',         'Guns N Roses',                1988, 'Rock Internacional'),
('Enter Sandman',              'Metallica',                   1991, 'Rock Internacional'),
('Wonderwall',                 'Oasis',                       1995, 'Rock Internacional'),
('Seven Nation Army',          'The White Stripes',           2003, 'Rock Internacional'),
('Livin on a Prayer',          'Bon Jovi',                    1986, 'Rock Internacional'),
('Don\'t Stop Believin\'',     'Journey',                     1981, 'Rock Internacional'),
('Eye of the Tiger',           'Survivor',                    1982, 'Rock Internacional'),
('Jump',                       'Van Halen',                   1984, 'Rock Internacional'),
('Pour Some Sugar on Me',      'Def Leppard',                 1987, 'Rock Internacional'),
('Under the Bridge',           'Red Hot Chili Peppers',       1992, 'Rock Internacional'),
('Black Hole Sun',             'Soundgarden',                 1994, 'Rock Internacional'),
('With or Without You',        'U2',                          1987, 'Rock Internacional'),
('Creep',                      'Radiohead',                   1992, 'Rock Internacional'),
('Mr. Brightside',             'The Killers',                 2003, 'Rock Internacional'),
('Last Resort',                'Papa Roach',                  2000, 'Rock Internacional'),
('In the End',                 'Linkin Park',                 2000, 'Rock Internacional'),

-- Pop/Rock Español
('En el Rio',                  'Vetusta Morla',               2011, 'Pop/Rock Español'),
('La Bicicleta',               'Carlos Vives & Shakira',      2016, 'Pop/Rock Español'),
('Macarena',                   'Los del Rio',                 1993, 'Pop/Rock Español'),
('Hijo de la Luna',            'Mecano',                      1986, 'Pop/Rock Español'),
('La Chica de Ayer',           'Nino Bravo',                  1973, 'Pop/Rock Español'),
('Un Año de Amor',             'Mecano',                      1988, 'Pop/Rock Español'),
('Resistire',                  'Duo Dinamico',                1988, 'Pop/Rock Español'),
('Corazon Partio',             'Alejandro Sanz',              1997, 'Pop/Rock Español'),
('Vivir',                      'Ricky Martin',                1995, 'Pop/Rock Español'),
('La Tortura',                 'Shakira ft. Alejandro Sanz',  2005, 'Pop/Rock Español'),
('A quien le importa',         'Alaska y Dinarama',           1986, 'Pop/Rock Español'),
('El Mar',                     'La Oreja de Van Gogh',        2000, 'Pop/Rock Español'),
('Antes de que cuente diez',   'Fito y Fitipaldis',           2009, 'Pop/Rock Español'),
('Me quedo contigo',           'Los Chunguitos',              1981, 'Pop/Rock Español'),
('Libre',                      'Nino Bravo',                  1972, 'Pop/Rock Español'),
('Mediterraneo',               'Joan Manuel Serrat',          1971, 'Pop/Rock Español'),
('Veneno en la piel',          'Radio Futura',                1990, 'Pop/Rock Español'),

-- 80s
('Sweet Dreams',               'Eurythmics',                  1983, '80s'),
('Thriller',                   'Michael Jackson',             1982, '80s'),
('Take On Me',                 'a-ha',                        1985, '80s'),
('Girls Just Want to Have Fun','Cyndi Lauper',                1983, '80s'),
('Like a Prayer',              'Madonna',                     1989, '80s'),
('Don\'t You Forget About Me', 'Simple Minds',                1985, '80s'),
('Every Breath You Take',      'The Police',                  1983, '80s'),
('Wake Me Up Before You Go-Go','Wham!',                       1984, '80s'),
('Tainted Love',               'Soft Cell',                   1981, '80s'),
('Come On Eileen',             'Dexys Midnight Runners',      1982, '80s'),
('West End Girls',             'Pet Shop Boys',               1985, '80s'),
('True',                       'Spandau Ballet',              1983, '80s'),
('Faith',                      'George Michael',              1987, '80s'),
('Never Gonna Give You Up',    'Rick Astley',                 1987, '80s'),
('Total Eclipse of the Heart', 'Bonnie Tyler',                1983, '80s'),
('Africa',                     'Toto',                        1982, '80s'),

-- New Age
('Watermark',                  'Enya',                        1988, 'New Age'),
('Oxygene',                    'Jean-Michel Jarre',           1976, 'New Age'),
('Tubular Bells',              'Mike Oldfield',               1973, 'New Age'),
('Orinoco Flow',               'Enya',                        1988, 'New Age'),
('Chariots of Fire',           'Vangelis',                    1981, 'New Age'),
('Return to Innocence',        'Enigma',                      1994, 'New Age'),
('Sadeness Part I',            'Enigma',                      1990, 'New Age'),
('May It Be',                  'Enya',                        2001, 'New Age'),
('Only Time',                  'Enya',                        2000, 'New Age'),
('Porcelain',                  'Moby',                        1999, 'New Age'),
('Teardrop',                   'Massive Attack',              1998, 'New Age'),
('Sweet Lullaby',              'Deep Forest',                 1992, 'New Age'),
('Adiemus',                    'Karl Jenkins',                1995, 'New Age'),
('Boadicea',                   'Enya',                        1987, 'New Age'),
('Song of the Sea',            'Clannad',                     1982, 'New Age'),

-- Rock en Español
('La Camisa Negra',            'Juanes',                      2004, 'Rock en Español'),
('Donde Jugaran los Ninos',    'Mana',                        1992, 'Rock en Español'),
('Clavado en un Bar',          'Mana',                        1994, 'Rock en Español'),
('Oye mi Amor',                'Mana',                        1992, 'Rock en Español'),
('Te Quiero',                  'Hombres G',                   1987, 'Rock en Español'),
('Devuelveme a mi Chica',      'Hombres G',                   1985, 'Rock en Español'),
('En el Muelle de San Blas',   'Mana',                        1994, 'Rock en Español'),
('Persiana Americana',         'Soda Stereo',                 1986, 'Rock en Español'),
('De Musica Ligera',           'Soda Stereo',                 1990, 'Rock en Español'),
('Cuando Pase el Temblor',     'Soda Stereo',                 1985, 'Rock en Español'),
('No Digas Nada',              'La Oreja de Van Gogh',        1998, 'Rock en Español'),
('Quiero Ser',                 'La Oreja de Van Gogh',        2003, 'Rock en Español'),
('El Universo Sobre Mi',       'Amaral',                      2005, 'Rock en Español'),
('La Flaca',                   'Jarabe de Palo',              1996, 'Rock en Español'),

-- Trap/Rap Internacional
('Stan',                       'Eminem',                      2000, 'Trap/Rap Internacional'),
('Lose Yourself',              'Eminem',                      2002, 'Trap/Rap Internacional'),
('HUMBLE.',                    'Kendrick Lamar',              2017, 'Trap/Rap Internacional'),
('Sicko Mode',                 'Travis Scott',                2018, 'Trap/Rap Internacional'),
('God\'s Plan',                'Drake',                       2018, 'Trap/Rap Internacional'),
('Rockstar',                   'Post Malone',                 2017, 'Trap/Rap Internacional'),
('Old Town Road',              'Lil Nas X',                   2019, 'Trap/Rap Internacional'),
('Lucid Dreams',               'Juice WRLD',                  2018, 'Trap/Rap Internacional'),
('Bad Guy',                    'Billie Eilish',               2019, 'Trap/Rap Internacional'),
('Hotline Bling',              'Drake',                       2015, 'Trap/Rap Internacional'),
('Sunflower',                  'Post Malone',                 2018, 'Trap/Rap Internacional'),
('Congratulations',            'Post Malone',                 2016, 'Trap/Rap Internacional'),
('Savage',                     'Megan Thee Stallion',         2020, 'Trap/Rap Internacional'),
('MONTERO',                    'Lil Nas X',                   2021, 'Trap/Rap Internacional'),

-- Trap/Rap en Español
('Krippy Kush',                'Bad Bunny & Farruko',         2017, 'Trap/Rap en Español'),
('Taki Taki',                  'DJ Snake ft. Ozuna',          2018, 'Trap/Rap en Español'),
('MIA',                        'Bad Bunny ft. Drake',         2018, 'Trap/Rap en Español'),
('Dakiti',                     'Bad Bunny & Jhay Cortez',     2020, 'Trap/Rap en Español'),
('Con Calma',                  'Daddy Yankee & Snow',         2019, 'Trap/Rap en Español'),
('Safaera',                    'Bad Bunny',                   2020, 'Trap/Rap en Español'),
('China',                      'Anuel AA ft. Bad Bunny',      2019, 'Trap/Rap en Español'),
('Callaita',                   'Bad Bunny',                   2019, 'Trap/Rap en Español'),
('Tusa',                       'Karol G & Nicki Minaj',       2019, 'Trap/Rap en Español'),
('Bichota',                    'Karol G',                     2020, 'Trap/Rap en Español'),
('Pa Ti',                      'Jhay Cortez & J. Balvin',     2020, 'Trap/Rap en Español'),
('Ojitos Lindos',              'Bad Bunny & Bomba Estereo',   2022, 'Trap/Rap en Español'),
('Gatubela',                   'Karol G & Maluma',            2021, 'Trap/Rap en Español'),

-- Actualidad
('Levitating',                 'Dua Lipa',                    2020, 'Actualidad'),
('Blinding Lights',            'The Weeknd',                  2020, 'Actualidad'),
('drivers license',            'Olivia Rodrigo',              2021, 'Actualidad'),
('Stay',                       'Kid Laroi & Justin Bieber',   2021, 'Actualidad'),
('As It Was',                  'Harry Styles',                2022, 'Actualidad'),
('Heat Waves',                 'Glass Animals',               2020, 'Actualidad'),
('Anti-Hero',                  'Taylor Swift',                2022, 'Actualidad'),
('Flowers',                    'Miley Cyrus',                 2023, 'Actualidad'),
('Cruel Summer',               'Taylor Swift',                2023, 'Actualidad'),
('Vampire',                    'Olivia Rodrigo',              2023, 'Actualidad'),
('Quevedo BZRP Session 52',    'Bizarrap & Quevedo',          2022, 'Actualidad'),
('Shakira BZRP Session 53',    'Bizarrap & Shakira',          2023, 'Actualidad'),
('TQG',                        'Karol G & Shakira',           2023, 'Actualidad'),
('Espresso',                   'Sabrina Carpenter',           2024, 'Actualidad'),
('Beautiful Things',           'Benson Boone',                2024, 'Actualidad'),
('Die With A Smile',           'Lady Gaga & Bruno Mars',      2024, 'Actualidad');
