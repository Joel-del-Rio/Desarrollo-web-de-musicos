# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Hitstoric** is a multiplayer music guessing game (inspired by Hitster) where players place songs on a personal timeline in chronological order. The application is a PHP-based SPA with a MySQL database, designed to run on XAMPP locally or SiteGround in production.

**Tech Stack:**
- Backend: PHP 8+ with PDO (MySQL)
- Frontend: Bootstrap 5.3 + Vanilla JS (no frameworks)
- Database: MySQL with auto-migration system
- Deployment: XAMPP (local, Windows) or SiteGround (production, Linux)

## Folder Structure

Web Musicos/
├── index.php                      # Front controller (routes all requests)
├── config.php                     # Database, email, and genre configuration
├── .htaccess                      # Apache URL rewriting (clean URLs)
├── Controlador/                   # Controllers (MVC logic layer)
│   ├── api.php                    # Single API entry point (routes ?action= to controllers)
│   ├── GameController.php         # Game creation, state management, admin actions
│   ├── PlayerController.php       # Player join, state polling, answer submission
│   ├── PrizeController.php        # Prize catalog, leaderboard, admin auth
│   └── SongController.php         # Song catalog management
├── Modelo/                        # Models (data layer)
│   ├── Database.php               # PDO singleton, auto-migration on startup
│   ├── Installer.php              # Database schema versioning & migrations (v0 to v12)
│   ├── Game.php                   # Game creation, round management, song selection
│   ├── Player.php                 # Player CRUD, timeline, answer evaluation, streaks
│   └── EmailService.php           # Transactional email (game creation, PIN distribution)
├── Vista/                         # Views (SPA templates)
│   ├── index.php                  # Home/landing page
│   ├── admin.php                  # Dynamizer (admin) control panel SPA
│   ├── player.php                 # Player mobile SPA
│   ├── songs.php                  # Song catalog management
│   ├── premios.php                # Prize admin panel
│   └── premios_catalog.php        # Public prize leaderboard
└── assets/
    ├── js/
    │   ├── admin.js               # Admin polling, game flow, state management
    │   └── player.js              # Player polling, timeline UI, answer mechanics
    ├── css/
    │   └── main.css               # Bootstrap overrides, custom styles
    └── images/premios/            # Uploaded prize images

## Core Architecture

### Request Flow

1. **URL Routing**: .htaccess rewrites to index.php?page=X or index.php?action=Y
2. **Front Controller** (index.php):
   - If ?action= detected: load Controlador/api.php
   - Otherwise: load requested Vista file
3. **API Entry Point** (Controlador/api.php):
   - Routes ?action= to appropriate Controller method
   - Returns JSON only
   - Catches exceptions and returns HTTP 500 with error message
4. **Controllers** (GameController, PlayerController, etc.):
   - Validate input
   - Delegate to Models for data operations
   - Return associative arrays (JSON-serialized by api.php)
5. **Models** (Game, Player, Database):
   - Execute SQL queries via PDO
   - Enforce business logic (scoring, streaks, validation)
   - Return results for Controllers

### Database Auto-Migration

When any page loads, Database::getInstance() in Modelo/Database.php:
1. Connects to MySQL (or creates DB if missing)
2. Calls Installer::run() which:
   - Reads schema_version table to get current version
   - Applies all pending migrations incrementally (v0 to v12)
   - Migrations are idempotent (checks, try-catch blocks)
3. Sets time_zone = '+00:00' to ensure consistent TIMESTAMP reads

**Current Schema Version: 12**
- v4: Streaming URLs (Spotify, YouTube)
- v5: Individual PIN mode & organizer email
- v6: Prize fields (prize_1, prize_2, prize_3) on games table
- v7: Global leaderboard (global_scores), prize catalog
- v8-9: Prize image support (emoji to image column)
- v10: Player email in individual_pins
- v11: Answer position tracking (position_guess, is_correct, points_earned)
- v12: Streak tracking for multiplier bonus

### SPA Pattern (Frontend)

Both admin.php and player.php are single-page applications:

- **Screen System**: Multiple div.screen elements; only one has .active class
- **Polling**: JavaScript calls player_state or game_state API every 1-2 seconds
- **State Dispatch**: Response from API triggers screen transitions and DOM updates
- **localStorage**: Persists player_id (PK), game_id (GK), admin_token (TK) across reloads

**Admin SPA Flow:**
setup -> waiting (polls) -> question (polls, timer countdown) -> results (polls) -> finished

**Player SPA Flow:**
join -> lobby (polls) -> question (polls, timeline interaction) -> answered -> results -> finished

### API Actions (Controlador/api.php)

**Game Management:**
- create_game (POST): GameController::createGame() - creates game, sends emails
- game_state (GET): GameController::getGameState() - admin polling
- game_state_by_pin (GET): GameController::getGameStateByPin() - player join validation
- start_game (POST): GameController::startGame() - admin starts round 1
- show_results (POST): GameController::showResults() - reveal song year
- next_round (POST): GameController::nextRound() - advance or finish

**Player Management:**
- join_game (POST): PlayerController::joinGame() - create player, validate PIN
- player_state (GET): PlayerController::getPlayerState() - polling
- submit_answer (POST): PlayerController::submitAnswer() - score position guess

**Prizes & Leaderboard:**
- get_prizes_catalog (GET): PrizeController::getCatalog() - public active prizes
- get_prizes_all (GET): PrizeController::getAll() - admin view
- save_prize (POST): PrizeController::save() - create/edit with image upload
- toggle_prize (POST): PrizeController::toggle() - activate/deactivate
- delete_prize (POST): PrizeController::delete() - soft delete
- get_global_leaderboard (GET): PrizeController::getLeaderboard() - top players
- get_my_score (GET): PrizeController::getMyScore() - player's points
- admin_login (POST): PrizeController::login() - prize panel auth

**Songs:**
- get_songs (GET): SongController::getSongs() - all songs
- update_song_links (POST): SongController::updateLinks() - Spotify/YouTube URLs

### Authentication & Authorization

1. **Admin Token**: Generated at game creation (bin2hex(random_bytes(32))), verified via hash_equals()
2. **PIN Modes**:
   - Shared: One PIN per game, unlimited players
   - Individual: Unique PIN per player in individual_pins table; prevents duplicates
3. **Prize Admin**: SHA-256 password hash in PrizeController::login(); session stored in localStorage

### Scoring & Streaks

When a player submits an answer (Modelo/Player.php::submitPositionAnswer):

1. **Correctness**: Compare position guess to actual chronological position in timeline
2. **Streak**: +1 if correct, reset to 0 if wrong
3. **Multiplier**:
   - Streak < 3: x1.0
   - Streak >= 3: x(1.0 + streak*0.1), capped at x2.0
4. **Points**: Base = 500 + (500 * timeLeft/questionTime); if correct: base * multiplier
5. **Timeline Update**: Add song to player_timeline if correct
6. **Global Points**: If individual PIN mode, UPSERT into global_scores table

### Deployment Considerations

**Local Development (Windows XAMPP):**
- config.php auto-detects via PHP_OS_FAMILY === 'Windows'
- DB: root (no password), hitster_musicos
- .htaccess RewriteBase: /Practicas/Web%20Musicos/
- Email: disabled (SMTP_ENABLED = false)

**Production (SiteGround Linux):**
- DB credentials hardcoded for SiteGround environment
- .htaccess RewriteBase: /
- Email: enabled via mail()
- BASE_URL calculated dynamically

**Key Gotchas:**
- .htaccess RewriteBase must match installation path or clean URLs break
- Email requires server configuration; disable SMTP_ENABLED when testing locally
- MySQL time_zone set to UTC ensures TIMESTAMP consistency across environments

## Important Code Patterns

### PDO Prepared Statements

All queries use prepared statements. Models receive `Database::getInstance()->pdo()` and call `prepare/execute/fetch`. Always pass values as positional `?` parameters, never interpolate into SQL.

### Error Handling

- Database errors throw PDOException (caught in api.php, returns HTTP 500 JSON)
- Controllers validate input before delegating to Models
- API always returns JSON; failures include an `'error'` key

### Idempotent Migrations

Migrations wrap each ALTER in try-catch so re-running on an already-migrated DB is safe.

### Race Conditions

- Game PINs: retry loop to avoid collisions
- Individual PINs: checked against both game PINs and other individual PINs
- Answers: INSERT IGNORE prevents duplicates on retransmit

## Development Workflow

### Running Locally

1. Start XAMPP (Apache + MySQL)
2. Navigate: http://localhost/Practicas/Web%20Musicos/
3. Entry Points: /admin (panel), /player (join), /songs (songs), /premios (prizes)

### Testing Changes

- **API**: Browser DevTools Network tab or curl
- **Database**: phpMyAdmin or MySQL CLI
- **localStorage**: DevTools -> Application -> Local Storage

### Common Tasks

**Add New API Action:**
1. Create method in Controller
2. Add case in Controlador/api.php
3. Echo json_encode() result

**Add Database Column:**
1. Find current schema_version in Installer.php
2. Add migration block (e.g., if (\ === 11))
3. Use ALTER TABLE ... ADD COLUMN
4. Increment schema_version
5. Next page load auto-runs migration

**Send Transactional Email:**
1. Call EmailService::sendGameCreated() in Controller
2. Edit template in Modelo/EmailService.php
3. SMTP_ENABLED in config.php controls mail() calls

## Debugging Tips

1. **Browser Console**: Check for JS errors
2. **Network Tab**: Watch API calls; HTTP 500 = backend exception
3. **Database**: Verify schema_version and table structure
4. **localStorage**: console.log(localStorage) for persisted state
5. **Server Logs**: XAMPP error log (local) or cPanel (SiteGround)

## Deployment Checklist

Before production:

1. Update .htaccess RewriteBase to /
2. Update database credentials in config.php
3. Set SMTP_ENABLED = true
4. Test email (game creation & PIN distribution)
5. Verify migrations run without errors
6. Test both shared and individual PIN modes
7. Verify leaderboard & prizes work with multiple games
8. Clear browser cache and localStorage
