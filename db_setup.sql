CREATE DATABASE IF NOT EXISTS hitster_musicos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hitster_musicos;

CREATE TABLE IF NOT EXISTS games (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pin         CHAR(4) NOT NULL,
    admin_token VARCHAR(64) NOT NULL,
    status      ENUM('waiting','question','results','finished') DEFAULT 'waiting',
    current_round       INT DEFAULT 0,
    total_rounds        INT DEFAULT 10,
    question_started_at TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pin (pin)
);

CREATE TABLE IF NOT EXISTS players (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    name         VARCHAR(50) NOT NULL,
    score        INT DEFAULT 0,
    avatar_color VARCHAR(7) DEFAULT '#FF6B6B',
    last_seen    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS songs (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    title  VARCHAR(200) NOT NULL,
    artist VARCHAR(200) NOT NULL,
    year   INT NOT NULL,
    genre  VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS game_songs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    song_id      INT NOT NULL,
    round_number INT NOT NULL,
    options      JSON NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS answers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    player_id    INT NOT NULL,
    song_id      INT NOT NULL,
    year_guess   INT NOT NULL,
    is_correct   TINYINT(1) DEFAULT 0,
    points_earned INT DEFAULT 0,
    answered_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (game_id, player_id, song_id),
    FOREIGN KEY (game_id)   REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (song_id)   REFERENCES songs(id)
);

-- Canciones de muestra (5 décadas, géneros variados)
INSERT INTO songs (title, artist, year, genre) VALUES
('Rock Around the Clock',       'Bill Haley & His Comets', 1954, 'Rock and Roll'),
('Johnny B. Goode',             'Chuck Berry',             1958, 'Rock and Roll'),
('La Bamba',                    'Ritchie Valens',          1958, 'Rock and Roll'),
('House of the Rising Sun',     'The Animals',             1964, 'Rock'),
('Yesterday',                   'The Beatles',             1965, 'Pop'),
('Purple Haze',                 'Jimi Hendrix',            1967, 'Rock'),
('Hey Jude',                    'The Beatles',             1968, 'Pop Rock'),
('Stairway to Heaven',          'Led Zeppelin',            1971, 'Rock'),
('Bohemian Rhapsody',           'Queen',                   1975, 'Rock'),
('Hotel California',            'Eagles',                  1977, 'Rock'),
('Don''t Stop Believin''',      'Journey',                 1981, 'Rock'),
('Thriller',                    'Michael Jackson',         1982, 'Pop'),
('Every Breath You Take',       'The Police',              1983, 'Pop'),
('Girls Just Want to Have Fun', 'Cyndi Lauper',            1983, 'Pop'),
('Like a Prayer',               'Madonna',                 1989, 'Pop'),
('Nothing Compares 2 U',        'Sinéad O''Connor',        1990, 'Pop'),
('Smells Like Teen Spirit',     'Nirvana',                 1991, 'Rock'),
('Wonderwall',                  'Oasis',                   1995, 'Britpop'),
('Killing Me Softly',           'Fugees',                  1996, 'Hip-Hop'),
('...Baby One More Time',       'Britney Spears',          1998, 'Pop'),
('No Scrubs',                   'TLC',                     1999, 'R&B'),
('Beautiful Day',               'U2',                      2000, 'Rock'),
('Crazy in Love',               'Beyoncé',                 2003, 'R&B'),
('Hey Ya!',                     'OutKast',                 2003, 'Hip-Hop'),
('Rolling in the Deep',         'Adele',                   2010, 'Pop'),
('Somebody That I Used to Know','Gotye',                   2011, 'Indie Pop'),
('Blurred Lines',               'Robin Thicke',            2013, 'Pop'),
('Shape of You',                'Ed Sheeran',              2017, 'Pop'),
('Old Town Road',               'Lil Nas X',               2019, 'Country Rap'),
('Drivers License',             'Olivia Rodrigo',          2021, 'Pop');
