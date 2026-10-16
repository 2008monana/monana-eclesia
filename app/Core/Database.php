<?php
namespace App\Core;

use PDO;
use PDOException;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Único ponto de ligação PDO à base MySQL/MariaDB. */
final class Database
{
    /** @var array<string,mixed> */
    private static $configuration = [];

    /** @var PDO|null */
    private static $connection;

    public static function configure(array $configuration): void
    {
        self::$configuration = $configuration;
        self::$connection = null;
    }

    /** Devolve uma ligação única, configurada para prepared statements nativos. */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = strtolower((string) (self::$configuration['driver'] ?? 'mysql'));
        if ($driver !== 'mysql') {
            throw new \RuntimeException('A aplicação suporta apenas MySQL/MariaDB nesta instalação.');
        }

        $host = trim((string) (self::$configuration['host'] ?? ''));
        $database = trim((string) (self::$configuration['database'] ?? ''));
        $username = (string) (self::$configuration['username'] ?? '');
        $password = (string) (self::$configuration['password'] ?? '');
        $port = (int) (self::$configuration['port'] ?? 3306);
        $charset = (string) (self::$configuration['charset'] ?? 'utf8mb4');

        if ($host === '' || $database === '' || $username === '' || $port < 1 || $port > 65535) {
            throw new \RuntimeException('A configuração da base de dados está incompleta.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
            throw new \RuntimeException('O charset configurado para a base de dados é inválido.');
        }

        $dsn = 'mysql:host=' . $host
            . ';port=' . $port
            . ';dbname=' . $database
            . ';charset=' . $charset;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            self::$connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            // A excepção original segue apenas para o log; não revelar DSN nem credenciais.
            throw new \RuntimeException('Não foi possível estabelecer ligação à base de dados.', 0, $exception);
        }

        return self::$connection;
    }

    /** Transacção simples para os Services; faz rollback em qualquer erro. */
    public static function transaction(callable $operation)
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            throw new \LogicException('Não é permitida uma transacção aninhada sem savepoint.');
        }

        $pdo->beginTransaction();
        try {
            $result = call_user_func($operation, $pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** Permite injecção de PDO em testes sem alterar a configuração global. */
    public static function useConnection(PDO $connection): void
    {
        self::$connection = $connection;
    }
}
