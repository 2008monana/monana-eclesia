<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Resposta HTTP, com cabeçalhos de segurança aplicados por defeito. */
final class Response
{
    /** @var int */
    private $status;

    /** @var array<string,string> */
    private $headers;

    /** @var string */
    private $body;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status >= 100 && $status <= 599 ? $status : 200;
        $this->headers = [];

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, (string) $value);
        }
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers);
        return new self($body, $status, $headers);
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=UTF-8'], $headers);
        return new self($body, $status, $headers);
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '{"erro":"Não foi possível preparar a resposta."}';
            $status = 500;
        }
        $headers = array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers);
        return new self($encoded, $status, $headers);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (strpos($location, "\r") !== false || strpos($location, "\n") !== false) {
            throw new \InvalidArgumentException('Destino de redireccionamento inválido.');
        }
        return new self('', $status, ['Location' => $location]);
    }

    /** Resposta para páginas legadas que definem os seus próprios cabeçalhos. */
    public static function legacy(string $body, int $status = 200): self
    {
        return new self($body, $status, []);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setHeader(string $name, string $value): self
    {
        if (!preg_match('/^[A-Za-z0-9-]+$/', $name)
            || strpos($value, "\r") !== false
            || strpos($value, "\n") !== false
        ) {
            throw new \InvalidArgumentException('Cabeçalho HTTP inválido.');
        }

        $this->headers[$name] = $value;
        return $this;
    }

    /** Emite os cabeçalhos sem o corpo; usado antes de incluir um módulo legado. */
    public static function applySecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
        header('X-Permitted-Cross-Domain-Policies: none', true);
    }

    /** Envia a resposta. O corpo é suprimido automaticamente para pedidos HEAD. */
    public function send(string $method = 'GET'): void
    {
        self::applySecurityHeaders();

        if (!headers_sent()) {
            http_response_code($this->status);
            $hasContentType = false;
            foreach (array_keys($this->headers) as $name) {
                if (strtolower($name) === 'content-type') {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                foreach (headers_list() as $existingHeader) {
                    if (stripos($existingHeader, 'Content-Type:') === 0) {
                        $hasContentType = true;
                        break;
                    }
                }
            }
            if (!$hasContentType) {
                header('Content-Type: text/html; charset=UTF-8', true);
            }
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if (strtoupper($method) !== 'HEAD') {
            echo $this->body;
        }
    }
}
