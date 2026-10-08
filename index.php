<?php
// index.php - Raiz do projeto
// Redireciona para o login usando o mesmo caminho base fixo do resto do sistema
require_once __DIR__ . '/config/url.php';
redirect('login');
