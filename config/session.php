<?php
// config/session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function checkLogin() {
    if (!isLoggedIn()) {
        redirect('login');
    }
}

/**
 * ============================================
 * CONTROLO DE ACESSO POR MÓDULO
 * ============================================
 * O administrador ('admin') tem sempre acesso a tudo. Os restantes perfis
 * (editor/visualizador, chamados aqui genericamente de "gestor") só têm
 * acesso aos módulos que o administrador lhes atribuir explicitamente, na
 * página de Utilizadores. A página de Perfil está sempre acessível a
 * qualquer utilizador com sessão iniciada, independentemente dos módulos
 * atribuídos - é o destino garantido de quem não tem mais nenhum acesso.
 *
 * Os módulos ficam gravados em `usuarios.modulos_permitidos` como uma
 * lista separada por vírgulas (ex: "members,registry,lists").
 */

// Lista de módulos que podem ser atribuídos a um gestor pelo administrador.
// 'users' e 'audit' ficam sempre restritos apenas a administradores.
function getModulosAtribuiveis() {
    return [
        'dashboard' => 'Dashboard',
        'members'   => 'Membros',
        'registry'  => 'Registo Diário de Cotas',
        'lists'     => 'Listas',
        'year_end'  => 'Contribuição de Fim de Ano',
        'reports'   => 'Relatórios',
        'expenses'  => 'Saídas/Despesas',
    ];
}

// Converte a string gravada na BD (ou a sessão actual) numa lista de módulos.
function getModulosPermitidos() {
    if (($_SESSION['user_perfil'] ?? '') === 'admin') {
        return array_keys(getModulosAtribuiveis());
    }
    $bruto = $_SESSION['user_modulos'] ?? '';
    if (is_array($bruto)) return $bruto;
    return array_values(array_filter(array_map('trim', explode(',', (string) $bruto))));
}

// 'profile' é sempre permitido; 'users' e 'audit' só para admin.
function podeAcessarModulo($modulo) {
    if (!isLoggedIn()) return false;
    if ($modulo === 'profile') return true;
    if (($_SESSION['user_perfil'] ?? '') === 'admin') return true;
    if (in_array($modulo, ['users', 'audit'], true)) return false;
    return in_array($modulo, getModulosPermitidos(), true);
}

/**
 * Deve ser chamada logo no topo de cada página protegida, passando a
 * chave do módulo (ex: 'members', 'registry'...). Redireciona para o
 * login se não houver sessão, ou para o perfil se o utilizador não tiver
 * acesso a esse módulo.
 */
function requireModuleAccess($modulo) {
    checkLogin();
    if (!podeAcessarModulo($modulo)) {
        redirect('modules/profile/index.php?sem_acesso=1');
    }
}

/**
 * Devolve a URL de destino ideal para o utilizador logo após o login:
 * administradores e quem tem o módulo 'dashboard' atribuído vão para o
 * dashboard; quem não tem vai directo para a sua página de perfil, que é
 * a única página sempre garantida.
 */
function getUrlPosLogin() {
    if (podeAcessarModulo('dashboard')) {
        return 'dashboard';
    }
    return 'profile';
}

function hasPermission($modulo, $acao) {
    // Mantida por compatibilidade: qualquer acção num módulo a que o
    // utilizador tenha acesso é permitida; sem acesso ao módulo, nada é.
    return podeAcessarModulo($modulo);
}
?>