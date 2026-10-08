<?php
// public/index.php
// ============================================
// FRONT CONTROLLER da arquitectura MVC
// ============================================
// Todos os pedidos que não correspondem a um ficheiro físico real
// (CSS, JS, imagens...) são encaminhados pelo .htaccess para aqui,
// onde o Router resolve a rota para Controlador::acção.

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/bootstrap.php';
require_once BASE_PATH . '/core/Router.php';

Router::dispatch();
