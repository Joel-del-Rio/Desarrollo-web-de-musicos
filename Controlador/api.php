<?php
/**
 * api.php — Punto de entrada único de la API REST
 *
 * Todas las peticiones AJAX del frontend (admin.js y player.js) llegan aquí.
 * Enruta la acción al controlador correspondiente y devuelve JSON.
 * Los errores no capturados devuelven HTTP 500 con el mensaje de excepción.
 */
require_once __DIR__ . '/../config.php';

// Cabeceras comunes para todas las respuestas
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Responder inmediatamente a las peticiones preflight de CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── Panel del dinamizador (admin) ──────────────

        case 'create_game':
            // Crea una nueva partida con la configuración del formulario
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->createGame());
            break;

        case 'list_public_games':
            // Lista las partidas públicas abiertas (navegador de servidores del jugador)
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->listPublicGames());
            break;

        case 'game_state':
            // Estado de la partida para el polling del admin (cada 1s durante pregunta)
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->getGameState());
            break;

        case 'game_state_by_pin':
            // Estado de la partida usando el PIN (usado por el jugador al hacer join)
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->getGameStateByPin());
            break;

        case 'start_game':
            // Inicia la partida: reparte canciones ancla y pasa a ronda 1
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->startGame());
            break;

        case 'show_results':
            // Revela el año de la canción y muestra los resultados de la ronda
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->showResults());
            break;

        case 'next_round':
            // Avanza a la siguiente ronda o finaliza la partida si era la última
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->nextRound());
            break;

        case 'kick_player':
            // Expulsa a un jugador de la sala de espera
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->kickPlayer());
            break;

        // ── Autenticación del panel de premios ─────────

        case 'admin_login':
            // Verifica credenciales del administrador de premios
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->login());
            break;

        // ── Premios globales ───────────────────────────

        case 'get_prizes_catalog':
            // Catálogo público de premios activos (vista jugadores)
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->getCatalog());
            break;

        case 'get_prizes_all':
            // Todos los premios incluyendo los ocultos (vista admin)
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->getAll());
            break;

        case 'save_prize':
            // Crea o edita un premio (soporta subida de imagen)
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->save());
            break;

        case 'toggle_prize':
            // Activa o desactiva la visibilidad de un premio
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->toggle());
            break;

        case 'delete_prize':
            // Elimina un premio definitivamente
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->delete());
            break;

        case 'get_global_leaderboard':
            // Ranking global de jugadores con más puntos acumulados
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->getLeaderboard());
            break;

        case 'get_my_score':
            // Puntos acumulados de un jugador identificado por email
            require_once __DIR__ . '/PrizeController.php';
            echo json_encode((new PrizeController())->getMyScore());
            break;

        // ── Superadmin ─────────────────────────────────

        case 'superadmin_login':
            require_once __DIR__ . '/SuperadminController.php';
            echo json_encode((new SuperadminController())->login());
            break;

        case 'superadmin_stats':
            require_once __DIR__ . '/SuperadminController.php';
            echo json_encode((new SuperadminController())->getStats());
            break;

        case 'superadmin_games':
            require_once __DIR__ . '/SuperadminController.php';
            echo json_encode((new SuperadminController())->getGames());
            break;

        case 'superadmin_game_detail':
            require_once __DIR__ . '/SuperadminController.php';
            echo json_encode((new SuperadminController())->getGameDetail());
            break;

        // ── Proxy iTunes (evita CORS en móviles) ──────

        case 'itunes_preview':
            $term = trim($_GET['term'] ?? '');
            if (!$term) { echo json_encode(['previewUrl' => null, 'artworkUrl' => null]); break; }
            $url  = 'https://itunes.apple.com/search?media=music&entity=song&limit=5&term=' . urlencode($term);
            $ctx  = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true,
                        'header' => 'User-Agent: Hitstoric/1.0']]);
            $raw  = @file_get_contents($url, false, $ctx);
            if ($raw === false) { echo json_encode(['previewUrl' => null, 'artworkUrl' => null]); break; }
            $data = json_decode($raw, true);
            $hit  = null;
            $art  = null;
            foreach (($data['results'] ?? []) as $t) {
                if (!$art && !empty($t['artworkUrl100'] ?? $t['artworkUrl60'] ?? null)) {
                    $art = $t['artworkUrl100'] ?? $t['artworkUrl60'];
                }
                if (!empty($t['previewUrl'])) { $hit = $t['previewUrl']; break; }
            }
            echo json_encode(['previewUrl' => $hit, 'artworkUrl' => $art]);
            break;

        // ── Canciones ──────────────────────────────────

        case 'get_songs':
            // Lista completa de canciones (panel de gestión)
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->getSongs());
            break;

        case 'update_song_links':
            // Actualiza los URLs de Spotify/YouTube de una canción
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->updateLinks());
            break;

        case 'add_song':
            // Añade una canción nueva al catálogo (buscador del panel superadmin)
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->addSong());
            break;

        case 'delete_song':
            // Elimina una canción del catálogo (panel superadmin)
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->deleteSong());
            break;

        case 'save_song_artwork':
            // Backfill progresivo: guarda la carátula encontrada en vivo para una canción antigua
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->saveArtwork());
            break;

        // ── Géneros ────────────────────────────────────

        case 'list_genres':
            // Lista de géneros con id (panel superadmin)
            require_once __DIR__ . '/GenreController.php';
            echo json_encode((new GenreController())->list());
            break;

        case 'add_genre':
            // Añade un género nuevo al catálogo
            require_once __DIR__ . '/GenreController.php';
            echo json_encode((new GenreController())->add());
            break;

        case 'rename_genre':
            // Renombra un género existente (actualiza canciones y partidas en cascada)
            require_once __DIR__ . '/GenreController.php';
            echo json_encode((new GenreController())->rename());
            break;

        // ── Memes (modo de juego alternativo) ──────────

        case 'get_memes':
            // Lista completa de memes (panel de gestión)
            require_once __DIR__ . '/MemeController.php';
            echo json_encode((new MemeController())->getMemes());
            break;

        case 'add_meme':
            // Sube una imagen nueva al catálogo de memes
            require_once __DIR__ . '/MemeController.php';
            echo json_encode((new MemeController())->addMeme());
            break;

        case 'delete_meme':
            // Elimina un meme del catálogo
            require_once __DIR__ . '/MemeController.php';
            echo json_encode((new MemeController())->deleteMeme());
            break;

        // ── Jugador ────────────────────────────────────

        case 'join_game':
            // Registra al jugador en la partida (por PIN compartido o individual)
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->joinGame());
            break;

        case 'player_state':
            // Estado completo del jugador para su polling (cada 1-2s)
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->getPlayerState());
            break;

        case 'update_avatar':
            // Cambia el avatar del jugador (solo permitido en la sala de espera)
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->updateAvatar());
            break;

        case 'update_customization':
            // Cambia pelo/gafas/sombrero/auriculares del jugador (solo en la sala de espera)
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->updateCustomization());
            break;

        case 'submit_answer':
            // Envía la posición elegida por el jugador para la canción actual
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->submitAnswer());
            break;

        case 'send_reaction':
            // Lanza una reacción (emoji) visible para todos los jugadores de la partida
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->sendReaction());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
    }
} catch (Throwable $e) {
    // Cualquier excepción no capturada devuelve 500 con el mensaje de error
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
