<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\MembroController;
use App\Controllers\DepartamentoController;
use App\Controllers\CelulaController;
use App\Controllers\UsuarioController;
use App\Controllers\ConfiguracaoController;
use App\Controllers\PerfilController;
use App\Controllers\EbdController;
use App\Controllers\EventoController;
use App\Controllers\FinanceiroController;
use App\Controllers\RelatorioController;
use App\Controllers\AuditoriaController;
use App\Controllers\AssinaturaController;
use App\Controllers\BackupController;
use App\Controllers\NotificacaoController;
use App\Controllers\SuperadminController;
use App\Controllers\SuperadminIgrejaController;
use App\Controllers\SuperadminSupervisorController;
use App\Controllers\SuperadminPlanoController;
use App\Controllers\SuperadminAssinaturaController;
use App\Controllers\SuperadminPagamentoController;
use App\Controllers\SuperadminAuditoriaController;
use App\Controllers\SuperadminConfiguracaoController;
/** @var \App\Core\Router $router */

// ============================================================
// Guest (visitantes)
// ============================================================
$router->group('', ['guest'], function ($r) {
    $r->get('/login', [AuthController::class, 'showUserLogin']);
    $r->post('/login', [AuthController::class, 'userLogin'], ['csrf']);
});

// Recuperação de senha
$router->get('/forgot',        [AuthController::class, 'showForgot']);
$router->post('/forgot',       [AuthController::class, 'sendReset'],     ['csrf']);
$router->get('/reset/{token}', [AuthController::class, 'showReset']);
$router->post('/reset',        [AuthController::class, 'resetPassword'], ['csrf']);

// Superadmin login
$router->get('/superadmin/login',  [AuthController::class, 'showSupervisorLogin']);
$router->post('/superadmin/login', [AuthController::class, 'supervisorLogin'], ['csrf']);

// ============================================================
// Autenticado (user)
// ============================================================
$router->post('/logout',       [AuthController::class, 'logout'],          ['auth', 'csrf']);
$router->get('/force-change',  [AuthController::class, 'showForceChange'], ['auth']);
$router->post('/force-change', [AuthController::class, 'forceChange'],     ['auth', 'csrf']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'tenant', 'subscription']);

// Membros
$router->get('/membros',                [MembroController::class, 'index'],   ['auth', 'tenant', 'perm:membros.ver']);
$router->get('/membros/criar',          [MembroController::class, 'create'],  ['auth', 'tenant', 'perm:membros.criar']);
$router->post('/membros',               [MembroController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:membros.criar']);
$router->get('/membros/{id}',           [MembroController::class, 'show'],    ['auth', 'tenant', 'perm:membros.ver']);
$router->get('/membros/{id}/editar',    [MembroController::class, 'edit'],    ['auth', 'tenant', 'perm:membros.editar']);
$router->post('/membros/{id}',          [MembroController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:membros.editar']);
$router->post('/membros/{id}/eliminar', [MembroController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:membros.eliminar']);
$router->post('/membros/{id}/familia',                     [MembroController::class, 'addFamilia'],    ['auth', 'tenant', 'csrf', 'perm:membros.editar']);
$router->post('/membros/{id}/familia/{parenteId}/remover', [MembroController::class, 'removeFamilia'], ['auth', 'tenant', 'csrf', 'perm:membros.editar']);

// Departamentos
$router->get('/departamentos',                [DepartamentoController::class, 'index'],   ['auth', 'tenant', 'perm:departamentos.ver']);
$router->get('/departamentos/criar',          [DepartamentoController::class, 'create'],  ['auth', 'tenant', 'perm:departamentos.criar']);
$router->post('/departamentos',               [DepartamentoController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:departamentos.criar']);
$router->get('/departamentos/{id}',           [DepartamentoController::class, 'show'],    ['auth', 'tenant', 'perm:departamentos.ver']);
$router->get('/departamentos/{id}/editar',    [DepartamentoController::class, 'edit'],    ['auth', 'tenant', 'perm:departamentos.editar']);
$router->post('/departamentos/{id}',          [DepartamentoController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:departamentos.editar']);
$router->post('/departamentos/{id}/eliminar', [DepartamentoController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:departamentos.eliminar']);
$router->post('/departamentos/{id}/membros',                    [DepartamentoController::class, 'addMembro'],    ['auth', 'tenant', 'csrf', 'perm:departamentos.editar']);
$router->post('/departamentos/{id}/membros/{memberId}/remover', [DepartamentoController::class, 'removeMembro'], ['auth', 'tenant', 'csrf', 'perm:departamentos.editar']);

// Células
$router->get('/celulas',                [CelulaController::class, 'index'],   ['auth', 'tenant', 'perm:celulas.ver']);
$router->get('/celulas/criar',          [CelulaController::class, 'create'],  ['auth', 'tenant', 'perm:celulas.criar']);
$router->post('/celulas',               [CelulaController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:celulas.criar']);
$router->get('/celulas/{id}',           [CelulaController::class, 'show'],    ['auth', 'tenant', 'perm:celulas.ver']);
$router->get('/celulas/{id}/editar',    [CelulaController::class, 'edit'],    ['auth', 'tenant', 'perm:celulas.editar']);
$router->post('/celulas/{id}',          [CelulaController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:celulas.editar']);
$router->post('/celulas/{id}/eliminar', [CelulaController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:celulas.eliminar']);
$router->post('/celulas/{id}/membros',                    [CelulaController::class, 'addMembro'],    ['auth', 'tenant', 'csrf', 'perm:celulas.editar']);
$router->post('/celulas/{id}/membros/{memberId}/remover', [CelulaController::class, 'removeMembro'], ['auth', 'tenant', 'csrf', 'perm:celulas.editar']);
$router->post('/celulas/{id}/reunioes',                   [CelulaController::class, 'addReuniao'],    ['auth', 'tenant', 'csrf', 'perm:celulas.editar']);
$router->post('/celulas/{id}/reunioes/{rid}/eliminar',    [CelulaController::class, 'removeReuniao'], ['auth', 'tenant', 'csrf', 'perm:celulas.editar']);

// Usuários
$router->get('/usuarios',                [UsuarioController::class, 'index'],   ['auth', 'tenant', 'perm:utilizadores.ver']);
$router->get('/usuarios/criar',          [UsuarioController::class, 'create'],  ['auth', 'tenant', 'perm:utilizadores.criar']);
$router->post('/usuarios',               [UsuarioController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:utilizadores.criar']);
$router->get('/usuarios/{id}',           [UsuarioController::class, 'show'],    ['auth', 'tenant', 'perm:utilizadores.ver']);
$router->get('/usuarios/{id}/editar',    [UsuarioController::class, 'edit'],    ['auth', 'tenant', 'perm:utilizadores.editar']);
$router->post('/usuarios/{id}',          [UsuarioController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:utilizadores.editar']);
$router->post('/usuarios/{id}/eliminar', [UsuarioController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:utilizadores.eliminar']);
$router->post('/usuarios/{id}/roles',                     [UsuarioController::class, 'saveRoles'],       ['auth', 'tenant', 'csrf', 'perm:utilizadores.atribuir_permissoes']);
$router->post('/usuarios/{id}/permissoes',                [UsuarioController::class, 'addPermission'],   ['auth', 'tenant', 'csrf', 'perm:utilizadores.atribuir_permissoes']);
$router->post('/usuarios/{id}/permissoes/{pid}/remover',  [UsuarioController::class, 'removePermission'], ['auth', 'tenant', 'csrf', 'perm:utilizadores.atribuir_permissoes']);
$router->post('/usuarios/{id}/sessoes/{sid}/revogar',     [UsuarioController::class, 'revokeSession'],   ['auth', 'tenant', 'csrf', 'perm:utilizadores.editar']);

// EBD
$router->get('/ebd',                [EbdController::class, 'index'],   ['auth', 'tenant', 'perm:ebd.ver']);
$router->get('/ebd/criar',          [EbdController::class, 'create'],  ['auth', 'tenant', 'perm:ebd.criar']);
$router->post('/ebd',               [EbdController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:ebd.criar']);
$router->get('/ebd/{id}',           [EbdController::class, 'show'],    ['auth', 'tenant', 'perm:ebd.ver']);
$router->get('/ebd/{id}/editar',    [EbdController::class, 'edit'],    ['auth', 'tenant', 'perm:ebd.editar']);
$router->post('/ebd/{id}',          [EbdController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:ebd.editar']);
$router->post('/ebd/{id}/eliminar', [EbdController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:ebd.eliminar']);
$router->post('/ebd/{id}/alunos',                         [EbdController::class, 'addAluno'],      ['auth', 'tenant', 'csrf', 'perm:ebd.editar']);
$router->post('/ebd/{id}/alunos/{memberId}/remover',      [EbdController::class, 'removeAluno'],   ['auth', 'tenant', 'csrf', 'perm:ebd.editar']);
$router->post('/ebd/{id}/professores',                    [EbdController::class, 'addProfessor'],  ['auth', 'tenant', 'csrf', 'perm:ebd.editar']);
$router->post('/ebd/{id}/professores/{memberId}/remover', [EbdController::class, 'removeProfessor'], ['auth', 'tenant', 'csrf', 'perm:ebd.editar']);

// Eventos
$router->get('/eventos',                [EventoController::class, 'index'],   ['auth', 'tenant', 'perm:eventos.ver']);
$router->get('/eventos/criar',          [EventoController::class, 'create'],  ['auth', 'tenant', 'perm:eventos.criar']);
$router->post('/eventos',               [EventoController::class, 'store'],   ['auth', 'tenant', 'csrf', 'perm:eventos.criar']);
$router->get('/eventos/{id}',           [EventoController::class, 'show'],    ['auth', 'tenant', 'perm:eventos.ver']);
$router->get('/eventos/{id}/editar',    [EventoController::class, 'edit'],    ['auth', 'tenant', 'perm:eventos.editar']);
$router->post('/eventos/{id}',          [EventoController::class, 'update'],  ['auth', 'tenant', 'csrf', 'perm:eventos.editar']);
$router->post('/eventos/{id}/eliminar', [EventoController::class, 'destroy'], ['auth', 'tenant', 'csrf', 'perm:eventos.eliminar']);
$router->post('/eventos/{id}/presenca/{memberId}', [EventoController::class, 'togglePresenca'], ['auth', 'tenant', 'csrf', 'perm:eventos.registar_presenca']);
$router->post('/eventos/{id}/presencas',           [EventoController::class, 'marcarTodos'],    ['auth', 'tenant', 'csrf', 'perm:eventos.registar_presenca']);

// Financeiro
$router->get('/financeiro',          [FinanceiroController::class, 'index'],    ['auth', 'tenant', 'perm:financeiro.ver']);
$router->get('/financeiro/exportar', [FinanceiroController::class, 'exportar'], ['auth', 'tenant', 'perm:financeiro.ver']);

// Categorias
$router->get('/financeiro/categorias',                [FinanceiroController::class, 'categorias'],       ['auth', 'tenant', 'perm:financeiro.ver']);
$router->get('/financeiro/categorias/criar',          [FinanceiroController::class, 'categoriaCreate'],  ['auth', 'tenant', 'perm:financeiro.criar']);
$router->post('/financeiro/categorias',               [FinanceiroController::class, 'categoriaStore'],   ['auth', 'tenant', 'csrf', 'perm:financeiro.criar']);
$router->get('/financeiro/categorias/{id}/editar',    [FinanceiroController::class, 'categoriaEdit'],    ['auth', 'tenant', 'perm:financeiro.editar']);
$router->post('/financeiro/categorias/{id}',          [FinanceiroController::class, 'categoriaUpdate'],  ['auth', 'tenant', 'csrf', 'perm:financeiro.editar']);
$router->post('/financeiro/categorias/{id}/eliminar', [FinanceiroController::class, 'categoriaDestroy'], ['auth', 'tenant', 'csrf', 'perm:financeiro.eliminar']);

// Transações
$router->get('/financeiro/transacoes',                [FinanceiroController::class, 'transacoes'],       ['auth', 'tenant', 'perm:financeiro.ver']);
$router->get('/financeiro/transacoes/criar',          [FinanceiroController::class, 'transacaoCreate'],  ['auth', 'tenant', 'perm:financeiro.criar']);
$router->post('/financeiro/transacoes',               [FinanceiroController::class, 'transacaoStore'],   ['auth', 'tenant', 'csrf', 'perm:financeiro.criar']);
$router->get('/financeiro/transacoes/{id}/editar',    [FinanceiroController::class, 'transacaoEdit'],    ['auth', 'tenant', 'perm:financeiro.editar']);
$router->post('/financeiro/transacoes/{id}',          [FinanceiroController::class, 'transacaoUpdate'],  ['auth', 'tenant', 'csrf', 'perm:financeiro.editar']);
$router->post('/financeiro/transacoes/{id}/eliminar', [FinanceiroController::class, 'transacaoDestroy'], ['auth', 'tenant', 'csrf', 'perm:financeiro.eliminar']);

// Fecho
$router->get('/financeiro/fecho',               [FinanceiroController::class, 'fecho'],         ['auth', 'tenant', 'perm:financeiro.fechar_mes']);
$router->post('/financeiro/fecho',              [FinanceiroController::class, 'executarFecho'], ['auth', 'tenant', 'csrf', 'perm:financeiro.fechar_mes']);
$router->post('/financeiro/fecho/{id}/reabrir', [FinanceiroController::class, 'reabrirFecho'],  ['auth', 'tenant', 'csrf', 'perm:financeiro.fechar_mes']);

// Relatórios
$router->get('/relatorios',                 [RelatorioController::class, 'index'],           ['auth', 'tenant', 'perm:relatorios.ver']);
$router->get('/relatorios/membros',         [RelatorioController::class, 'membros'],         ['auth', 'tenant', 'perm:relatorios.ver']);
$router->get('/relatorios/financeiro',      [RelatorioController::class, 'financeiro'],      ['auth', 'tenant', 'perm:relatorios.ver']);
$router->get('/relatorios/frequencia',      [RelatorioController::class, 'frequencia'],      ['auth', 'tenant', 'perm:relatorios.ver']);
$router->get('/relatorios/aniversariantes', [RelatorioController::class, 'aniversariantes'], ['auth', 'tenant', 'perm:relatorios.ver']);
$router->get('/relatorios/exportar/{tipo}', [RelatorioController::class, 'exportar'],        ['auth', 'tenant', 'perm:relatorios.exportar']);

// Auditoria
$router->get('/auditoria', [AuditoriaController::class, 'index'], ['auth', 'tenant', 'perm:auditoria.ver']);

// Notificações
$router->get('/notificacoes', [NotificacaoController::class, 'index'], ['auth', 'tenant', 'perm:assinaturas.ver']);

// Assinatura
$router->get('/assinatura',        [AssinaturaController::class, 'index'], ['auth', 'tenant', 'perm:assinaturas.ver']);
$router->post('/assinatura/pagar', [AssinaturaController::class, 'pagar'], ['auth', 'tenant', 'csrf', 'perm:assinaturas.ver']);

// Backups
$router->get('/backups',                 [BackupController::class, 'index'],     ['auth', 'tenant', 'perm:backup.ver']);
$router->post('/backups/executar',       [BackupController::class, 'executar'],  ['auth', 'tenant', 'csrf', 'perm:backup.executar']);
$router->get('/backups/{id}/download',   [BackupController::class, 'download'],  ['auth', 'tenant', 'perm:backup.ver']);
$router->post('/backups/{id}/restaurar', [BackupController::class, 'restaurar'], ['auth', 'tenant', 'csrf', 'perm:backup.restaurar']);
$router->post('/backups/{id}/eliminar',  [BackupController::class, 'eliminar'],  ['auth', 'tenant', 'csrf', 'perm:backup.executar']);

// Configurações
$router->get('/configuracoes',         [ConfiguracaoController::class, 'index'],      ['auth', 'tenant', 'perm:configuracoes.ver']);
$router->post('/configuracoes/igreja', [ConfiguracaoController::class, 'saveIgreja'], ['auth', 'tenant', 'csrf', 'perm:configuracoes.editar']);
$router->post('/configuracoes/tema',   [ConfiguracaoController::class, 'saveTema'],   ['auth', 'tenant', 'csrf', 'perm:configuracoes.editar']);
$router->post('/configuracoes/smtp',   [ConfiguracaoController::class, 'saveSmtp'],   ['auth', 'tenant', 'csrf', 'perm:configuracoes.editar']);
$router->post('/configuracoes/logo',   [ConfiguracaoController::class, 'uploadLogo'], ['auth', 'tenant', 'csrf', 'perm:configuracoes.editar']);

// Perfil
$router->get('/perfil',                        [PerfilController::class, 'show'],         ['auth', 'tenant']);
$router->post('/perfil/dados',                 [PerfilController::class, 'saveDados'],    ['auth', 'tenant', 'csrf']);
$router->post('/perfil/senha',                 [PerfilController::class, 'saveSenha'],    ['auth', 'tenant', 'csrf']);
$router->post('/perfil/sessoes/{sid}/revogar', [PerfilController::class, 'revokeSession'], ['auth', 'tenant', 'csrf']);

// ============================================================
// Autenticado (supervisor) — Painel Superadmin
// ============================================================
$router->post('/superadmin/logout', [AuthController::class, 'supervisorLogout'], ['supervisor', 'csrf']);

$router->get('/superadmin', [SuperadminController::class, 'dashboard'], ['supervisor']);

// Igrejas (superadmin)
$router->get('/superadmin/igrejas',                 [SuperadminIgrejaController::class, 'index'],     ['supervisor']);
$router->get('/superadmin/igrejas/criar',           [SuperadminIgrejaController::class, 'create'],    ['supervisor']);
$router->post('/superadmin/igrejas',                [SuperadminIgrejaController::class, 'store'],     ['supervisor', 'csrf']);
$router->get('/superadmin/igrejas/{id}',            [SuperadminIgrejaController::class, 'show'],      ['supervisor']);
$router->get('/superadmin/igrejas/{id}/editar',     [SuperadminIgrejaController::class, 'edit'],      ['supervisor']);
$router->post('/superadmin/igrejas/{id}',           [SuperadminIgrejaController::class, 'update'],    ['supervisor', 'csrf']);
$router->post('/superadmin/igrejas/{id}/suspender', [SuperadminIgrejaController::class, 'suspender'], ['supervisor', 'csrf']);
$router->post('/superadmin/igrejas/{id}/reativar',  [SuperadminIgrejaController::class, 'reativar'],  ['supervisor', 'csrf']);
$router->post('/superadmin/igrejas/{id}/cancelar',  [SuperadminIgrejaController::class, 'cancelar'],  ['supervisor', 'csrf']);
$router->post('/superadmin/igrejas/{id}/reenviar-credenciais', [SuperadminIgrejaController::class, 'reenviarCredenciais'], ['supervisor', 'csrf']);
$router->get('/superadmin/igrejas/{id}/impersonate',[SuperadminIgrejaController::class, 'impersonate'], ['supervisor']);

// Supervisores (superadmin)
$router->get('/superadmin/supervisores',                 [SuperadminSupervisorController::class, 'index'],     ['supervisor']);
$router->get('/superadmin/supervisores/criar',           [SuperadminSupervisorController::class, 'create'],    ['supervisor']);
$router->post('/superadmin/supervisores',                [SuperadminSupervisorController::class, 'store'],     ['supervisor', 'csrf']);
$router->get('/superadmin/supervisores/{id}/editar',     [SuperadminSupervisorController::class, 'edit'],      ['supervisor']);
$router->post('/superadmin/supervisores/{id}',           [SuperadminSupervisorController::class, 'update'],    ['supervisor', 'csrf']);
$router->post('/superadmin/supervisores/{id}/bloquear',  [SuperadminSupervisorController::class, 'bloquear'],  ['supervisor', 'csrf']);
$router->post('/superadmin/supervisores/{id}/eliminar',  [SuperadminSupervisorController::class, 'destroy'],   ['supervisor', 'csrf']);

// Planos (superadmin)
$router->get('/superadmin/planos',                [SuperadminPlanoController::class, 'index'],   ['supervisor']);
$router->get('/superadmin/planos/criar',          [SuperadminPlanoController::class, 'create'],  ['supervisor']);
$router->post('/superadmin/planos',               [SuperadminPlanoController::class, 'store'],   ['supervisor', 'csrf']);
$router->get('/superadmin/planos/{id}/editar',    [SuperadminPlanoController::class, 'edit'],    ['supervisor']);
$router->post('/superadmin/planos/{id}',          [SuperadminPlanoController::class, 'update'],  ['supervisor', 'csrf']);
$router->post('/superadmin/planos/{id}/eliminar', [SuperadminPlanoController::class, 'destroy'], ['supervisor', 'csrf']);
// Assinaturas (superadmin)
$router->get('/superadmin/assinaturas',                  [SuperadminAssinaturaController::class, 'index'],   ['supervisor']);
$router->get('/superadmin/assinaturas/criar',            [SuperadminAssinaturaController::class, 'create'],  ['supervisor']);
$router->post('/superadmin/assinaturas',                 [SuperadminAssinaturaController::class, 'store'],   ['supervisor', 'csrf']);
$router->get('/superadmin/assinaturas/{id}',             [SuperadminAssinaturaController::class, 'show'],    ['supervisor']);
$router->post('/superadmin/assinaturas/{id}/renovar',    [SuperadminAssinaturaController::class, 'renovar'], ['supervisor', 'csrf']);
$router->post('/superadmin/assinaturas/{id}/suspender',  [SuperadminAssinaturaController::class, 'suspender'], ['supervisor', 'csrf']);
$router->post('/superadmin/assinaturas/{id}/reativar',   [SuperadminAssinaturaController::class, 'reativar'], ['supervisor', 'csrf']);
$router->post('/superadmin/assinaturas/{id}/cancelar',   [SuperadminAssinaturaController::class, 'cancelar'], ['supervisor', 'csrf']);

// Pagamentos (superadmin)
$router->get('/superadmin/pagamentos',                       [SuperadminPagamentoController::class, 'index'],     ['supervisor']);
$router->get('/superadmin/pagamentos/{id}',                  [SuperadminPagamentoController::class, 'show'],      ['supervisor']);
$router->post('/superadmin/pagamentos/{id}/confirmar',       [SuperadminPagamentoController::class, 'confirmar'], ['supervisor', 'csrf']);
$router->post('/superadmin/pagamentos/{id}/rejeitar',        [SuperadminPagamentoController::class, 'rejeitar'],  ['supervisor', 'csrf']);
// Auditoria Global (superadmin)
$router->get('/superadmin/auditoria',           [SuperadminAuditoriaController::class, 'index'],    ['supervisor']);
$router->get('/superadmin/auditoria/exportar',  [SuperadminAuditoriaController::class, 'exportar'], ['supervisor']);

// Configurações Globais (superadmin)
$router->get('/superadmin/configuracoes',       [SuperadminConfiguracaoController::class, 'index'],     ['supervisor']);
$router->post('/superadmin/configuracoes/geral',[SuperadminConfiguracaoController::class, 'saveGeral'], ['supervisor', 'csrf']);
$router->post('/superadmin/configuracoes/smtp', [SuperadminConfiguracaoController::class, 'saveSmtp'],  ['supervisor', 'csrf']);
// ============================================================
// Raiz
// ============================================================
$router->get('/', function () {
    redirect('/login');
});