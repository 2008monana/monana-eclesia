<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Escapa qualquer valor para apresentar em HTML. */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
