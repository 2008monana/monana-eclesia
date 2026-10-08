<?php
// config/url.php
// Configuração central de URLs do sistema

// ============================================
// CAMINHO BASE DA APLICAÇÃO
// ============================================
// Pasta onde o projecto está instalado no servidor.
//   - Local (XAMPP/MAMP): http://localhost/monana-eclesia
//   - Domínio raiz (InfinityFree etc.): defina APP_BASE como ''
if (!defined('APP_BASE')) {
    define('APP_BASE', '/monana-eclesia');
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . rtrim(APP_BASE, '/');

/**
 * Normaliza um caminho legado para a rota amigável correspondente.
 *   modules/members/index.php  -> members
 *   modules/auth/login.php     -> login
 *   assets/img/logo.png        -> assets/img/logo.png
 */
function esconder_php($path) {
    $path = ltrim($path, '/');
    if (substr($path, -4) === '.php') {
        $path = substr($path, 0, -4);
    }
    // separar a query string para a voltar a colar no fim
    $query = '';
    if (strpos($path, '?') !== false) {
        [$path, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }
    // aliases individuais de auth
    if ($path === 'modules/auth/login'            || $path === 'modules/auth/logout'
        || $path === 'modules/auth/forgot-password' || $path === 'modules/auth/reset-password') {
        return basename($path) . $query;
    }
    // modulos: remove o prefixo "modules/" e "index" redundante
    if (strpos($path, 'modules/') === 0) {
        $rest = substr($path, strlen('modules/'));
        if (substr($rest, -6) === '/index') {
            $rest = substr($rest, 0, -6);
        }
        return $rest . $query;
    }
    return $path . $query;
}

// Função para gerar URLs absolutos a partir de um caminho legítimo do disco
// (usado p/ assets físicos). Não aplica normalização de rotas.
function asset($path) {
    global $base_url;
    return $base_url . '/' . ltrim($path, '/');
}

// Função para gerar URLs de páginas/assets (sempre com o caminho base)
function url($path) {
    global $base_url;
    return $base_url . '/' . esconder_php($path);
}

// Caminho relativo ao base (útil em JS: history.replaceState etc.)
function url_relative($path) {
    global $base_url;
    return $base_url . '/' . esconder_php($path);
}

// Função para redirecionar
function redirect($path) {
    global $base_url;
    header('Location: ' . $base_url . '/' . esconder_php($path));
    exit;
}

// Função de debug - mostrar configuração
function debug_url() {
    global $protocol, $host, $base_url;
    echo "<h3>Configuração de URL</h3>";
    echo "<ul>";
    echo "<li><strong>Protocolo:</strong> " . $protocol . "</li>";
    echo "<li><strong>Host:</strong> " . $host . "</li>";
    echo "<li><strong>Base URL:</strong> " . $base_url . "</li>";
    echo "</ul>";
}
?>