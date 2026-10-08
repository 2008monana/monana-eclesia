<?php
// config/database.php (ambiente de teste local)
if (!function_exists('getConnection')) {
    function getConnection(): PDO {
        return new PDO('sqlite::memory:');
    }
}
