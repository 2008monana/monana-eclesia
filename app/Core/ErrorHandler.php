<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Regista erros em storage/logs sem expor detalhes ao visitante. */
final class ErrorHandler
{
    /** @var string */
    private static $logFile = '';

    /** @var bool */
    private static $debug = false;

    public static function register(string $logDirectory, bool $debug = false): void
    {
        self::$logFile = rtrim($logDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app.log';
        self::$debug = $debug;

        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        self::ensureLogDirectory();

        set_error_handler(static function ($severity, $message, $file, $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            self::log('PHP', (string) $message, [
                'ficheiro' => (string) $file,
                'linha' => (int) $line,
                'severidade' => (int) $severity,
            ]);
            // Evita que warnings/notices PHP sejam impressos na resposta.
            return true;
        });

        set_exception_handler(static function (\Throwable $exception): void {
            self::logException($exception);
            self::sendFatalResponse();
        });

        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            if (!is_array($lastError)) {
                return;
            }
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array((int) $lastError['type'], $fatalTypes, true)) {
                return;
            }
            self::log('FATAL', (string) $lastError['message'], [
                'ficheiro' => (string) $lastError['file'],
                'linha' => (int) $lastError['line'],
            ]);
            self::sendFatalResponse();
        });
    }

    public static function logException(\Throwable $exception): void
    {
        self::log('EXCEPTION', get_class($exception) . ': ' . $exception->getMessage(), [
            'ficheiro' => $exception->getFile(),
            'linha' => $exception->getLine(),
            'stack' => $exception->getTraceAsString(),
        ]);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'data' => date('Y-m-d H:i:s'),
            'nivel' => strtoupper($level),
            'mensagem' => self::limit($message, 4000),
            'contexto' => $context,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            $line = date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . self::limit($message, 4000);
        }
        $line .= PHP_EOL;

        self::ensureLogDirectory();
        if (self::$logFile !== '' && @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
        error_log(trim($line));
    }

    private static function sendFatalResponse(): void
    {
        if (headers_sent()) {
            return;
        }

        Response::applySecurityHeaders();
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8', true);
        $detail = '';
        if (self::$debug) {
            $detail = '<pre style="white-space:pre-wrap">Detalhes técnicos disponíveis apenas no log do servidor.</pre>';
        }
        echo '<!doctype html><html lang="pt-AO"><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Erro — MonanaEclésia</title><body style="font-family:Inter,Arial,sans-serif;'
            . 'background:#fbf8f1;color:#3a271a;padding:2rem">'
            . '<h1>Não foi possível concluir o pedido.</h1>'
            . '<p>O erro foi registado. Tenta novamente ou contacta o administrador.</p>'
            . $detail . '</body></html>';
    }

    private static function ensureLogDirectory(): void
    {
        if (self::$logFile === '') {
            return;
        }
        $directory = dirname(self::$logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
    }

    private static function limit(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max) . '…';
    }
}
