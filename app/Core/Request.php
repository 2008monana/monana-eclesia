<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Representação imutável do pedido HTTP recebido. */
final class Request
{
    /** @var string */
    private $method;

    /** @var string */
    private $path;

    /** @var array<string,mixed> */
    private $query;

    /** @var array<string,mixed> */
    private $body;

    /** @var array<string,string> */
    private $headers;

    /** @var array<string,mixed> */
    private $server;

    /** @var string */
    private $rawBody;

    /** @var array<string,string> */
    private $routeParameters = [];

    /** @param array<string,mixed> $query
     *  @param array<string,mixed> $body
     *  @param array<string,string> $headers
     *  @param array<string,mixed> $server
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = [],
        string $rawBody = ''
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalisePath($path);
        $this->query = $query;
        $this->body = $body;
        $this->rawBody = $rawBody;
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
        $this->server = $server;
    }

    /** Cria o pedido a partir das variáveis globais do PHP. */
    public static function fromGlobals(array $appConfig = []): self
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $basePath = isset($appConfig['base_path']) ? (string) $appConfig['base_path'] : '';
        $basePath = self::normaliseBasePath($basePath);
        if ($basePath !== '') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (strncmp($path, $basePath . '/', strlen($basePath) + 1) === 0) {
                $path = substr($path, strlen($basePath));
            }
        }

        // O index da raiz e o front controller podem ser pedidos directamente.
        if ($path === '/index.php' || $path === '/public/index.php') {
            $path = '/';
        }
        $path = self::normaliseLegacyPath($path);

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody)) {
            $rawBody = '';
        }
        $body = $_POST;
        $contentType = strtolower((string) ($headers['content-type'] ?? ''));
        if (strpos($contentType, 'application/json') !== false && $rawBody !== '') {
            $decodedBody = json_decode($rawBody, true);
            if (is_array($decodedBody)) {
                $body = array_replace($body, $decodedBody);
            }
        }

        return new self(
            isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET',
            $path,
            $_GET,
            $body,
            $headers,
            $_SERVER,
            $rawBody
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Caminho sem domínio, query string nem prefixo APP_BASE_PATH. */
    public function path(): string
    {
        return $this->path;
    }

    /** Devolve um valor enviado no corpo do pedido. */
    public function post(string $key, $default = null)
    {
        return array_key_exists($key, $this->body) ? $this->body[$key] : $default;
    }

    /** Devolve um parâmetro da query string. */
    public function query(string $key, $default = null)
    {
        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    /** Procura primeiro no corpo e depois na query string. */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }
        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    /** Devolve todos os dados do pedido, com o corpo a ter prioridade. */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** Corpo bruto, útil para verificar assinaturas ou formatos específicos. */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** Corpo JSON descodificado ou null se o pedido não contiver um objecto válido. */
    public function jsonBody(): ?array
    {
        if ($this->rawBody === '') {
            return null;
        }
        $decoded = json_decode($this->rawBody, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function header(string $name, $default = null)
    {
        $key = strtolower(str_replace('_', '-', $name));
        return array_key_exists($key, $this->headers) ? $this->headers[$key] : $default;
    }

    public function ip(): string
    {
        return isset($this->server['REMOTE_ADDR']) ? (string) $this->server['REMOTE_ADDR'] : '';
    }

    public function userAgent(): string
    {
        return (string) $this->header('user-agent', '');
    }

    /** Não confia em X-Forwarded-Proto sem uma lista de proxies confiáveis. */
    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }
        return isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443;
    }

    /** Parâmetro de rota já descodificado pelo Router. */
    public function route(string $key, $default = null)
    {
        return array_key_exists($key, $this->routeParameters) ? $this->routeParameters[$key] : $default;
    }

    /** Define os parâmetros para o controlador que tratará esta rota. */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    /** Devolve a query string original sem o carácter inicial ?. */
    public function queryString(): string
    {
        return isset($this->server['QUERY_STRING']) ? (string) $this->server['QUERY_STRING'] : '';
    }

    /** Traduz pedidos antigos que chegam internamente ao front controller. */
    private static function normaliseLegacyPath(string $path): string
    {
        $trimmed = trim($path, '/');
        $authAliases = [
            'modules/auth/login' => 'login',
            'modules/auth/logout' => 'logout',
            'modules/auth/forgot-password' => 'recuperar-senha',
            'modules/auth/reset-password' => 'resetar-senha',
        ];
        $legacyPath = preg_replace('/\\.php$/i', '', $trimmed);
        if (isset($authAliases[strtolower((string) $legacyPath)])) {
            return '/' . $authAliases[strtolower((string) $legacyPath)];
        }

        if (strpos(strtolower($trimmed), 'modules/') === 0) {
            $legacyPath = preg_replace('/\\.php$/i', '', $trimmed);
            $legacyPath = substr((string) $legacyPath, strlen('modules/'));
            $segments = array_values(array_filter(explode('/', $legacyPath), 'strlen'));
            if ($segments !== [] && strtolower((string) end($segments)) === 'index') {
                array_pop($segments);
            }
            if ($segments !== []) {
                return '/' . strtolower(implode('/', $segments));
            }
        }

        if (preg_match('/\\.php$/i', $path) && $path !== '/index.php' && $path !== '/public/index.php') {
            $path = substr($path, 0, -4);
        }
        return $path;
    }

    private static function normalisePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }
        $path = preg_replace('#/+#', '/', $path);
        return is_string($path) ? $path : '/';
    }

    private static function normaliseBasePath(string $basePath): string
    {
        $basePath = trim($basePath);
        if ($basePath === '' || $basePath === '/') {
            return '';
        }
        return '/' . trim(preg_replace('#/+#', '/', $basePath), '/');
    }
}
