<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'success',
    'timestamp' => time(),
    'datetime' => date('c'),
    'php_version' => PHP_VERSION,
    'query_params' => $_GET,
    'message' => 'API پاسخ داد؛ ارتباط اسکریپت PHP در زیرمسیر بدون مشکل برقرار است.'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
