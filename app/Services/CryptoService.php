<?php
namespace App\Services;

use App\Core\Config;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Cifra tokens da fila de email; os tokens persistidos na BD ficam cifrados. */
final class CryptoService
{
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'monana-email-fila-v1';

    public function isConfigured(): bool
    {
        $configured = trim((string) Config::env('APP_ENCRYPTION_KEY', ''));
        return preg_match('/^[a-f0-9]{64}$/i', $configured) === 1
            && function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt');
    }

    /** Cifra um array em formato versionado, autenticado e codificado em Base64. */
    public function encrypt(array $payload): string
    {
        $key = $this->key();
        $plainText = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plainText)) {
            throw new \RuntimeException('Não foi possível preparar os dados cifrados do email.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        if (!is_string($cipherText) || strlen($tag) !== 16) {
            throw new \RuntimeException('Não foi possível cifrar os dados sensíveis do email.');
        }
        return 'v1.' . base64_encode($iv . $tag . $cipherText);
    }

    /** Desfaz apenas payloads válidos com a mesma chave de aplicação. */
    public function decrypt(string $encrypted): array
    {
        if (strpos($encrypted, 'v1.') !== 0) {
            throw new \RuntimeException('O conteúdo cifrado do email não tem uma versão suportada.');
        }
        $binary = base64_decode(substr($encrypted, 3), true);
        if (!is_string($binary) || strlen($binary) < 29) {
            throw new \RuntimeException('O conteúdo cifrado do email está inválido.');
        }

        $iv = substr($binary, 0, 12);
        $tag = substr($binary, 12, 16);
        $cipherText = substr($binary, 28);
        $plainText = openssl_decrypt($cipherText, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD);
        if (!is_string($plainText)) {
            throw new \RuntimeException('Não foi possível decifrar os dados do email; confirma APP_ENCRYPTION_KEY.');
        }

        $payload = json_decode($plainText, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Os dados decifrados do email não são válidos.');
        }
        return $payload;
    }

    private function key(): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Define APP_ENCRYPTION_KEY com um segredo aleatório antes de enfileirar emails.');
        }
        $configured = trim((string) Config::env('APP_ENCRYPTION_KEY', ''));
        if (preg_match('/^[a-f0-9]{64}$/i', $configured)) {
            $binary = hex2bin($configured);
            if (is_string($binary) && strlen($binary) === 32) {
                return $binary;
            }
        }
        return hash('sha256', $configured, true);
    }
}
