<?php
class Database {
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct() {
        require_once dirname(__DIR__) . '/config.php';

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Base de datos no existe → ejecutar instalador automático
            if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() == 1049) {
                require_once __DIR__ . '/Installer.php';
                Installer::run(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                // Reconectar tras la instalación
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } else {
                throw $e;
            }
        }
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
