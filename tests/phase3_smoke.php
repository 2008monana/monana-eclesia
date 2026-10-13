<?php
/** Smoke tests sem dependência de MySQL; executar com `php tests/phase3_smoke.php`. */
if (!defined('MONANA')) {
    define('MONANA', true);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/app/Core/Autoload.php';
\App\Core\Autoload::register(BASE_PATH . '/app');
require_once BASE_PATH . '/app/helpers.php';

$passed = 0;
$failed = 0;
$test = static function (string $name, callable $operation) use (&$passed, &$failed): void {
    try {
        $operation();
        $passed++;
        echo "PASS: {$name}\n";
    } catch (\Throwable $exception) {
        $failed++;
        echo "FAIL: {$name} — {$exception->getMessage()}\n";
    }
};
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
};

$test('Validator aceita campos válidos e expõe passes()', static function () use ($expect): void {
    $validator = new \App\Core\Validator([
        'email' => 'admin@example.org',
        'nome' => 'Igreja do Centro',
        'limite' => '12',
    ]);
    $expect($validator->validate([
        'email' => 'required|email|max:191',
        'nome' => 'required|string|min:3|max:100',
        'limite' => 'required|integer|min:0',
    ]), 'A validação devia passar.');
    $expect($validator->passes(), 'passes() devia devolver true após validação válida.');
});

$test('Validator rejeita email inválido', static function () use ($expect): void {
    $validator = new \App\Core\Validator(['email' => 'nao-e-email']);
    $expect(!$validator->validate(['email' => 'required|email']), 'O email inválido foi aceite.');
    $expect(!$validator->passes(), 'passes() devia devolver false após erro.');
});

$test('PasswordHasher usa hash verificável e rehash estável', static function () use ($expect): void {
    $password = 'Senha segura de teste 2026!';
    $hash = \App\Services\PasswordHasher::hash($password);
    $expect($hash !== $password, 'A senha foi guardada sem hash.');
    $expect(\App\Services\PasswordHasher::verify($password, $hash), 'A senha correcta não verificou.');
    $expect(!\App\Services\PasswordHasher::verify('senha errada', $hash), 'Uma senha errada verificou.');
    $expect(!\App\Services\PasswordHasher::needsRehash($hash), 'O hash recém-criado pediu rehash.');
    $expect(\App\Services\PasswordHasher::isAcceptable(str_repeat('x', 12)), 'A senha mínima válida foi rejeitada.');
    if (\App\Services\PasswordHasher::algorithm() === PASSWORD_BCRYPT) {
        $expect(!\App\Services\PasswordHasher::isAcceptable(str_repeat('x', 73)), 'O limite de 72 bytes do bcrypt não foi aplicado.');
    }
    $bcryptHash = password_hash(str_repeat('y', 72), PASSWORD_BCRYPT);
    $expect(!\App\Services\PasswordHasher::verify(str_repeat('y', 73), $bcryptHash), 'bcrypt aceitou uma senha com sufixo após 72 bytes.');
});

$test('CSRF aceita token de sessão e rejeita token incorrecto', static function () use ($expect): void {
    \App\Core\Session::configure([
        'name' => 'monana_test_session',
        'lifetime_seconds' => 1800,
        'base_path' => '',
        'secure_cookie' => false,
    ]);
    \App\Core\Session::start();
    $token = str_repeat('a', 64);
    \App\Core\Session::set('_csrf_token', $token);
    $middleware = new \App\Middleware\CsrfMiddleware();
    $valid = new \App\Core\Request('POST', '/login', [], ['_csrf' => $token]);
    $invalid = new \App\Core\Request('POST', '/login', [], ['_csrf' => str_repeat('b', 64)]);
    $expect($middleware->handle($valid) === null, 'Token válido foi rejeitado.');
    $response = $middleware->handle($invalid);
    $expect($response instanceof \App\Core\Response && $response->status() === 419, 'Token inválido não devolveu 419.');
});

$test('Páginas legadas recebem CSRF em formulário e AJAX', static function () use ($expect): void {
    $html = '<html><head></head><body><form method="POST" action="/antigo"><button>Guardar</button></form></body></html>';
    $protected = \App\Middleware\CsrfMiddleware::injectIntoLegacyHtml($html);
    $expect(strpos($protected, 'name="_csrf"') !== false, 'Falta campo CSRF no formulário legado.');
    $expect(strpos($protected, 'meta name="csrf-token"') !== false, 'Falta meta-token para AJAX.');
    $expect(strpos($protected, 'X-CSRF-Token') !== false, 'Falta protecção de cabeçalho AJAX.');
});

$test('Payloads de email são cifrados, autenticados e exigem uma chave forte', static function () use ($expect): void {
    putenv('APP_ENCRYPTION_KEY=chave-curta');
    $service = new \App\Services\CryptoService();
    $expect(!$service->isConfigured(), 'Uma chave curta foi considerada válida.');
    putenv('APP_ENCRYPTION_KEY=' . bin2hex(random_bytes(32)));
    $payload = ['tipo' => 'activacao', 'token' => str_repeat('c', 64)];
    $encrypted = $service->encrypt($payload);
    $expect(strpos($encrypted, $payload['token']) === false, 'O token aparece em claro no payload.');
    $expect($service->decrypt($encrypted) === $payload, 'O payload cifrado não foi recuperado correctamente.');
    $binary = base64_decode(substr($encrypted, 3), true);
    $binary[15] = chr(ord($binary[15]) ^ 1);
    $tampered = 'v1.' . base64_encode($binary);
    $rejected = false;
    try {
        $service->decrypt($tampered);
    } catch (\Throwable $exception) {
        $rejected = true;
    }
    $expect($rejected, 'Um payload adulterado foi aceite.');
});

$test('TenantMiddleware nunca lê o tenant do pedido', static function () use ($expect): void {
    \App\Core\Session::set('_auth_user', ['papel' => 'admin_igreja', 'igreja_id' => 37]);
    $expect(\App\Middleware\TenantMiddleware::id() === 37, 'Tenant da sessão não foi devolvido.');
    \App\Core\Session::set('_auth_user', ['papel' => 'superadmin', 'igreja_id' => null]);
    $expect(\App\Middleware\TenantMiddleware::id() === null, 'Superadmin não devia ter tenant.');
});

$test('RBAC distingue Superadmin de papéis de igreja', static function () use ($expect): void {
    $expect(\App\Middleware\PapelMiddleware::allows('superadmin', ['superadmin']), 'Superadmin não foi autorizado.');
    $expect(!\App\Middleware\PapelMiddleware::allows('admin_igreja', ['superadmin']), 'Admin da igreja foi promovido a Superadmin.');
});

\App\Core\Session::destroy();
echo "\nResultado: {$passed} passou(aram), {$failed} falhou(aram).\n";
exit($failed === 0 ? 0 : 1);
