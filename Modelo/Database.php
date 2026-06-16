<?php
class Database {
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct() {
        require_once dirname(__DIR__) . '/config.php';
        require_once __DIR__ . '/Installer.php';

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() == 1049) {
                Installer::run(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
                $this->pdo->exec("SET time_zone = '+00:00'");
                return;
            }
            throw $e;
        }

        // Fijar zona horaria de la sesión MySQL a UTC para que los campos TIMESTAMP
        // se lean siempre como UTC, independientemente del servidor
        $this->pdo->exec("SET time_zone = '+00:00'");

        // Ejecutar migraciones pendientes en cada arranque
        Installer::run(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO {
        return $this->pdo;
    }
}
