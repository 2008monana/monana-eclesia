<?php
/** Smoke tests de vistas Fase 4 sem MySQL; executar com `php tests/phase4_layout_smoke.php`. */
if (!defined('MONANA')) {
    define('MONANA', true);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/app/Core/Autoload.php';
\App\Core\Autoload::register(BASE_PATH . '/app');
require_once BASE_PATH . '/app/helpers.php';
\App\Core\Config::set('app', ['url' => 'https://monana.test', 'base_path' => '']);
require_once BASE_PATH . '/config/url.php';
\App\Core\Session::configure([
    'name' => 'monana_phase4_test',
    'lifetime_seconds' => 1800,
    'base_path' => '',
    'secure_cookie' => false,
]);
\App\Core\Session::start();

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
$view = new \App\Core\View();

$test('Layout da igreja partilha o tema inline, CSRF e conteúdo escapado', static function () use ($view, $expect): void {
    $html = $view->render('layouts/app', [
        'title' => '<script>alert(1)</script>',
        'area' => 'igreja',
        'active' => 'painel',
        'actorName' => '<img src=x onerror=alert(1)>',
        'actorRole' => 'admin_igreja',
        'tenantName' => 'Igreja de Teste',
        'content' => '<p class="fase4-smoke">Conteúdo MVC</p>',
    ]);
    $expect(strpos($html, '--gold-500:  #c99a2e') !== false, 'O tema Monana não foi injectado inline.');
    $expect(!preg_match('/<link[^>]+monana-theme\.css/i', $html), 'O tema foi convertido para uma folha externa.');
    $expect(strpos($html, 'name="csrf-token"') !== false, 'Falta o meta-token CSRF.');
    $expect(strpos($html, 'name="_csrf"') !== false, 'Faltam campos CSRF nos formulários POST.');
    $expect(strpos($html, 'aria-controls="appSidebar"') !== false, 'Falta o controlo acessível do menu responsivo.');
    $expect(strpos($html, 'href="https://monana.test/membros"') === false, 'Um módulo ainda não migrado ganhou uma ligação activa.');
    $expect(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'O título não foi escapado.');
    $expect(strpos($html, '&lt;img src=x onerror=alert(1)&gt;') !== false, 'O nome do utilizador não foi escapado.');
    $expect(strpos($html, '<p class="fase4-smoke">Conteúdo MVC</p>') !== false, 'O conteúdo interno não foi composto no layout.');
});

$test('Layout Superadmin reutiliza o shell sem tenant e assinala a rota activa', static function () use ($view, $expect): void {
    $html = $view->render('layouts/superadmin', [
        'title' => 'Igrejas',
        'active' => 'igrejas',
        'actorName' => 'Conta de Teste',
        'content' => '<div class="fase4-platform-smoke">Plataforma</div>',
    ]);
    $expect(strpos($html, 'href="https://monana.test/superadmin/igrejas" aria-current="page"') !== false, 'A ligação activa de Igrejas não foi marcada.');
    $expect(strpos($html, 'Superadmin') !== false, 'O papel Superadmin não aparece no layout.');
    $expect(strpos($html, 'Tenant #') === false, 'A plataforma recebeu uma identificação de tenant.');
    $expect(strpos($html, 'name="_csrf"') !== false, 'Os formulários da plataforma não têm CSRF.');
});

$test('Emails de activação e redefinição usam o frame comum e escapam os dados', static function () use ($view, $expect): void {
    $activation = $view->render('emails/activacao', [
        'name' => '<script>nome</script>',
        'churchName' => 'Igreja & Teste',
        'activationLink' => '{{LINK}}',
    ]);
    $reset = $view->render('emails/redefinicao-senha', [
        'name' => 'Utilizador de Teste',
        'resetLink' => '{{LINK}}',
    ]);
    $expect(strpos($activation, '<html lang="pt-AO">') !== false, 'A activação não usa o frame email partilhado.');
    $expect(strpos($activation, 'Igreja &amp; Teste') !== false, 'O nome da igreja não foi escapado no email.');
    $expect(strpos($activation, '&lt;script&gt;nome&lt;/script&gt;') !== false, 'O nome do destinatário não foi escapado no email.');
    $expect(strpos($activation, '{{LINK}}') !== false && strpos($reset, '{{LINK}}') !== false, 'O marcador seguro de link deixou de estar disponível para a fila.');
    $expect(strpos($reset, 'expira em 60 minutos') !== false, 'O prazo de redefinição não aparece no email.');
    $expect(strpos($activation, 'https://') === false, 'O email de activação depende de um recurso externo.');
});

$test('Layout PDF usa logótipo embutido, identidade por igreja e escapa metadados', static function () use ($view, $expect): void {
    $html = $view->render('pdf/layout', [
        'title' => '<Relatório>',
        'subtitle' => 'Resumo & estado',
        'churchName' => '<Igreja de Teste>',
        'churchShortName' => 'IP Teste',
        'generatedAt' => '07/02/2026',
        'content' => '<p class="pdf-smoke">Conteúdo do relatório</p>',
    ]);
    $expect(strpos($html, 'data:image/png;base64,') !== false, 'O logótipo PDF não foi embutido.');
    $expect(strpos($html, '&lt;Igreja de Teste&gt;') !== false, 'O nome de igreja do PDF não foi escapado.');
    $expect(strpos($html, '&lt;Relatório&gt;') !== false, 'O título de PDF não foi escapado.');
    $expect(strpos($html, '07/02/2026') !== false, 'A data não respeita o formato dd/mm/aaaa.');
    $expect(strpos($html, '<p class="pdf-smoke">Conteúdo do relatório</p>') !== false, 'O corpo do PDF não foi composto.');
    $invalidDateRejected = false;
    try {
        $view->render('pdf/layout', ['generatedAt' => '2026-02-07']);
    } catch (\InvalidArgumentException $exception) {
        $invalidDateRejected = true;
    }
    $expect($invalidDateRejected, 'Uma data fora do formato dd/mm/aaaa foi aceite.');
});

\App\Core\Session::destroy();
echo "\nResultado: {$passed} passou(aram), {$failed} falhou(aram).\n";
exit($failed === 0 ? 0 : 1);
