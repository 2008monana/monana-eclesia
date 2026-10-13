<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Router explícito: apenas as rotas registadas em routes/web.php são executadas. */
final class Router
{
    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    /** @var array<int,array<string,mixed>> */
    private $routes = [];

    /** @var bool */
    private $debug;

    public function __construct(Request $request, array $appConfig = [], ?Response $response = null)
    {
        $this->request = $request;
        $this->response = $response ?: new Response();
        $this->debug = !empty($appConfig['debug']);
    }

    public function get(string $path, $handler): self
    {
        return $this->add(['GET'], $path, $handler);
    }

    public function post(string $path, $handler): self
    {
        return $this->add(['POST'], $path, $handler);
    }

    public function put(string $path, $handler): self
    {
        return $this->add(['PUT'], $path, $handler);
    }

    public function delete(string $path, $handler): self
    {
        return $this->add(['DELETE'], $path, $handler);
    }

    /** Regista uma rota de compatibilidade que aceita qualquer método HTTP. */
    public function any(string $path, $handler): self
    {
        return $this->add(['*'], $path, $handler);
    }

    /** Procura e executa a rota, sem enviar a resposta antes do front controller. */
    public function dispatch(): Response
    {
        $method = $this->request->method();
        $methodToMatch = $method === 'HEAD' ? 'GET' : $method;
        $pathMatched = false;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $this->match($route['path'], $this->request->path());
            if ($parameters === null) {
                continue;
            }

            $pathMatched = true;
            if (in_array('*', $route['methods'], true)) {
                $csrfResponse = (new \App\Middleware\CsrfMiddleware())->handle($this->request);
                if ($csrfResponse instanceof Response) {
                    return $csrfResponse;
                }
                return $this->runHandler($route['handler'], $parameters);
            }

            foreach ($route['methods'] as $allowed) {
                $allowedMethods[] = $allowed;
            }

            if (in_array($methodToMatch, $route['methods'], true)) {
                $csrfResponse = (new \App\Middleware\CsrfMiddleware())->handle($this->request);
                if ($csrfResponse instanceof Response) {
                    return $csrfResponse;
                }
                return $this->runHandler($route['handler'], $parameters);
            }
        }

        if ($pathMatched) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            if (in_array('GET', $allowedMethods, true) && !in_array('HEAD', $allowedMethods, true)) {
                $allowedMethods[] = 'HEAD';
            }
            sort($allowedMethods);
            return Response::text('Método não permitido.', 405, [
                'Allow' => implode(', ', $allowedMethods),
            ]);
        }

        return $this->notFound();
    }

    private function add(array $methods, string $path, $handler): self
    {
        if (!is_callable($handler)
            && !(is_array($handler) && count($handler) === 2 && is_string($handler[0]))
        ) {
            throw new \InvalidArgumentException('O destino da rota não é válido.');
        }

        $normalised = '/' . trim($path, '/');
        if ($normalised === '//') {
            $normalised = '/';
        }
        $this->routes[] = [
            'methods' => $methods,
            'path' => $normalised,
            'handler' => $handler,
        ];
        return $this;
    }

    /** Devolve parâmetros da rota ou null quando o caminho não corresponde. */
    private function match(string $pattern, string $path): ?array
    {
        $regex = '#^';
        $offset = 0;
        $found = preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $pattern, $tokens, PREG_OFFSET_CAPTURE);
        if ($found === false) {
            return null;
        }

        if ($found > 0) {
            foreach ($tokens[0] as $index => $token) {
                $tokenText = $token[0];
                $tokenOffset = $token[1];
                $regex .= preg_quote(substr($pattern, $offset, $tokenOffset - $offset), '#');
                $parameterName = $tokens[1][$index][0];
                $regex .= '(?P<' . $parameterName . '>[^/]+)';
                $offset = $tokenOffset + strlen($tokenText);
            }
        }

        $regex .= preg_quote(substr($pattern, $offset), '#') . '$#D';
        $matches = [];
        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $decoded = rawurldecode((string) $value);
                if ($decoded === '' || strpos($decoded, '/') !== false || $decoded === '.' || $decoded === '..') {
                    return null;
                }
                $parameters[$key] = $decoded;
            }
        }
        return $parameters;
    }

    private function runHandler($handler, array $parameters): Response
    {
        try {
            $this->request->setRouteParameters($parameters);

            if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
                $className = $handler[0];
                $methodName = $handler[1];
                if (!class_exists($className) || !is_subclass_of($className, Controller::class)) {
                    return $this->notFound();
                }

                $controller = new $className($this->request, $this->response);
                if (!is_string($methodName) || !is_callable([$controller, $methodName])) {
                    return $this->notFound();
                }
                $result = $controller->{$methodName}();
            } else {
                $result = call_user_func($handler, $this->request, $parameters);
            }

            if ($result instanceof Response) {
                return $result;
            }
            if (is_string($result)) {
                return Response::html($result);
            }
            if ($result === null) {
                return new Response('', 204);
            }

            throw new \UnexpectedValueException('O controlador devolveu um tipo de resposta não suportado.');
        } catch (\Throwable $exception) {
            ErrorHandler::logException($exception);
            return $this->serverError($exception);
        }
    }

    private function notFound(): Response
    {
        try {
            $view = new View();
            return Response::html($view->render('errors/404'), 404);
        } catch (\Throwable $exception) {
            ErrorHandler::logException($exception);
            return Response::html(
                '<!doctype html><html lang="pt-AO"><meta charset="utf-8">'
                . '<title>Não encontrado — MonanaEclésia</title>'
                . '<body><h1>Página não encontrada</h1></body></html>',
                404
            );
        }
    }

    private function serverError(\Throwable $exception): Response
    {
        try {
            $view = new View();
            return Response::html($view->render('errors/500', [
                'debug' => $this->debug,
                'exceptionMessage' => $exception->getMessage(),
            ]), 500);
        } catch (\Throwable $viewException) {
            ErrorHandler::logException($viewException);
            $message = $this->debug
                ? '<pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
                : '';
            return Response::html(
                '<!doctype html><html lang="pt-AO"><meta charset="utf-8">'
                . '<title>Erro — MonanaEclésia</title><body><h1>Não foi possível concluir o pedido.</h1>'
                . '<p>O erro foi registado. Tenta novamente ou contacta o administrador.</p>'
                . $message . '</body></html>',
                500
            );
        }
    }
}
