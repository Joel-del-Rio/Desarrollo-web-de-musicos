<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        /* ── Admin ── */
        case 'create_game':
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->createGame());
            break;

        case 'game_state':
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->getGameState());
            break;

        case 'start_game':
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->startGame());
            break;

        case 'show_results':
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->showResults());
            break;

        case 'next_round':
            require_once __DIR__ . '/GameController.php';
            echo json_encode((new GameController())->nextRound());
            break;

        /* ── Canciones ── */
        case 'get_songs':
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->getSongs());
            break;

        case 'update_song_links':
            require_once __DIR__ . '/SongController.php';
            echo json_encode((new SongController())->updateLinks());
            break;

        /* ── Jugador ── */
        case 'join_game':
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->joinGame());
            break;

        case 'player_state':
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->getPlayerState());
            break;

        case 'submit_answer':
            require_once __DIR__ . '/PlayerController.php';
            echo json_encode((new PlayerController())->submitAnswer());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
