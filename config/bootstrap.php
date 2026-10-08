<?php
// config/bootstrap.php
// ============================================
// PONTO DE ARRANQUE COMUM (bootstrap) da arquitectura MVC
// ============================================
// Todos os pedidos HTTP passam por aqui: carrega as dependências globais
// do sistema (sessão, base de dados, URLs) e devolve a ligação PDO.

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/url.php';      // url(), asset(), redirect()
require_once BASE_PATH . '/config/session.php';  // sessão + controlo de acesso
require_once BASE_PATH . '/config/database.php'; // getConnection()

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php'; // dompdf etc.
}

/**
 * Devolve a ligação PDO à base de dados.
 */
function db(): PDO
{
    return getConnection();
}
