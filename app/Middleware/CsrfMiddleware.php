<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Protecção synchronizer-token para formulários e pedidos AJAX. */
final class CsrfMiddleware
{
    private const SESSION_KEY = '_csrf_token';

    /** Cria/devolve o token da sessão para formulários e cabeçalhos AJAX. */
    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /** Renova o token após autenticação ou mudança de privilégio. */
    public static function rotate(): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /** Devolve null em pedidos seguros, ou uma resposta 419 se o token falhar. */
    public function handle(Request $request): ?Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        Session::start();
        $expected = Session::get(self::SESSION_KEY);
        $provided = $request->header('x-csrf-token');
        if (!is_string($provided) || $provided === '') {
            $provided = $request->post('_csrf', '');
        }

        $valid = is_string($expected)
            && is_string($provided)
            && strlen($expected) === 64
            && strlen($provided) === 64
            && hash_equals($expected, $provided);
        if ($valid) {
            return null;
        }

        $accept = strtolower((string) $request->header('accept', ''));
        if (strpos($accept, 'application/json') !== false
            || strtolower((string) $request->header('x-requested-with', '')) === 'xmlhttprequest'
        ) {
            return Response::json(['erro' => 'A sessão expirou. Actualiza a página e tenta novamente.'], 419);
        }
        return Response::text('A sessão expirou. Actualiza a página e tenta novamente.', 419);
    }

    /** Adiciona tokens a formulários e AJAX de páginas legadas ainda não migradas. */
    public static function injectIntoLegacyHtml(string $html): string
    {
        $token = self::token();
        $safeToken = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $field = '<input type="hidden" name="_csrf" value="' . $safeToken . '">';
        $hasPostForm = false;

        $html = preg_replace_callback(
            '~<form\b[^>]*>.*?</form\s*>~is',
            static function (array $matches) use ($field, &$hasPostForm): string {
                $form = $matches[0];
                if (!preg_match('~\bmethod\s*=\s*(?:"post"|\'post\'|post)(?:\s|>)~i', $form)) {
                    return $form;
                }
                $hasPostForm = true;
                if (preg_match('~\bname\s*=\s*(?:"_csrf"|\'_csrf\'|_csrf)~i', $form)) {
                    return $form;
                }
                return preg_replace('~(<form\b[^>]*>)~i', '$1' . $field, $form, 1);
            },
            $html
        );

        $meta = '<meta name="csrf-token" content="' . $safeToken . '">';
        if (stripos($html, '<meta name="csrf-token"') === false) {
            if (preg_match('~</head\s*>~i', $html)) {
                $html = preg_replace('~</head\s*>~i', $meta . '</head>', $html, 1);
            } else {
                $html = $meta . $html;
            }
        }

        $script = <<<'HTML'
<script>
(function () {
    var tag = document.querySelector('meta[name="csrf-token"]');
    if (!tag) return;
    var token = tag.getAttribute('content');
    var mutating = /^(POST|PUT|PATCH|DELETE)$/i;
    var originalFetch = window.fetch;
    if (typeof originalFetch === 'function') {
        window.fetch = function (input, init) {
            init = init || {};
            var urlValue = input && input.url ? input.url : input;
            var url;
            try { url = new URL(urlValue, window.location.href); } catch (error) { return originalFetch(input, init); }
            var method = (init.method || (input && input.method) || 'GET').toUpperCase();
            if (url.origin === window.location.origin && mutating.test(method)) {
                var headers = new Headers(init.headers || (input && input.headers) || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
            return originalFetch(input, init);
        };
    }
    if (window.XMLHttpRequest) {
        var open = XMLHttpRequest.prototype.open;
        var send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) {
            this.__monanaMethod = method;
            this.__monanaUrl = url;
            return open.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            try {
                var url = new URL(this.__monanaUrl || '', window.location.href);
                if (url.origin === window.location.origin && mutating.test(this.__monanaMethod || 'GET')) {
                    this.setRequestHeader('X-CSRF-Token', token);
                }
            } catch (error) {}
            return send.apply(this, arguments);
        };
    }
}());
</script>
HTML;
        if (stripos($html, '</body>') !== false && stripos($html, 'X-CSRF-Token') === false) {
            $html = preg_replace('~</body\s*>~i', $script . '</body>', $html, 1);
        }
        return $html;
    }
}
