<?php

declare(strict_types=1);

$backend = dirname(__DIR__, 2) . '/backend/public/index.php';
if (!is_file($backend)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Backend is not deployed']);
    exit;
}

require $backend;
