<?php

declare(strict_types=1);

/**
 * Constantes globais (caminhos + enums da BD).
 * Este ficheiro TEM de estar em monana-eclesia/config/constants.php.
 */

// Caminhos
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'config');
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
define('PUBLIC_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'public');
define('VIEWS_PATH', APP_PATH . DIRECTORY_SEPARATOR . 'Views');

// Enums (espelham a BD)
define('IGREJA_STATUS', ['em_teste', 'activa', 'suspensa', 'cancelada']);
define('SUBSCRIPTION_STATUS', ['trial', 'activa', 'expirando', 'expirada', 'em_tolerancia', 'suspensa', 'cancelada']);
define('MEMBER_STATUS', ['activo', 'inactivo', 'transferido', 'falecido']);
define('TRANSACTION_TIPO', ['entrada', 'despesa']);
define('PAYMENT_ESTADO', ['pendente', 'confirmado', 'rejeitado', 'reembolsado']);
define('SYSTEM_ROLES', ['admin_igreja', 'pastor', 'lider', 'financeiro', 'secretaria', 'membro']);
define('SUPERVISOR_NIVEL', ['superadmin', 'supervisor']);

// Segurança
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_MINUTES', 15);