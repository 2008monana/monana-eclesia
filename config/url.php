<?php
// config/url.php
// Configuração central de URLs do sistema

// ============================================
// CAMINHO BASE DA APLICAÇÃO
// ============================================
// A URL base é o domínio SEM a subpasta
// O .htaccess vai redirecionar internamente para /ipfva-gestao
// ============================================
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host;

// Função para gerar URLs de assets (CSS, JS, imagens)
function asset($path) {
    global $base_url;
    return $base_url . '/' . ltrim($path, '/');
}

// Remove a extensão .php do caminho, para gerar URLs amigáveis
// (o .htaccess trata do redirecionamento/mapeamento interno)
function esconder_php($path) {
    $path = ltrim($path, '/');
    if (substr($path, -4) === '.php') {
        $path = substr($path, 0, -4);
    }
    return $path;
}

// Função para gerar URLs de páginas
function url($path) {
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