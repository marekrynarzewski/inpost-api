<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/ShipmentWorkflow.php';

use FocusGarden\ShipmentWorkflow;

header('Content-Type: application/json; charset=utf-8');

try {
    $rawBody = file_get_contents('php://input');
    $input = [];

    if (is_string($rawBody) && $rawBody !== '') {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $input = is_array($decoded) ? $decoded : [];
    }

    $workflow = new ShipmentWorkflow(dirname(__DIR__));
    $result = $workflow->run($input);

    http_response_code(($result['status'] ?? '') === 'error' ? 422 : 200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
