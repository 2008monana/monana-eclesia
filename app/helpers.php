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

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = \App\Middleware\CsrfMiddleware::token();
        return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
    }
}

if (!function_exists('csrf_meta')) {
    function csrf_meta(): string
    {
        return '<meta name="csrf-token" content="' . e(\App\Middleware\CsrfMiddleware::token()) . '">';
    }
}
