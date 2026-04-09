<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Mrynarzewski\InpostApi\RequestLogger;
use Mrynarzewski\InpostApi\ShipmentWorkflow;

header('Content-Type: application/json; charset=utf-8');

try {
    $rawBody = file_get_contents('php://input');
    $input = [];

    if (is_string($rawBody) && $rawBody !== '') {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $input = is_array($decoded) ? $decoded : [];
    }

    RequestLogger::log(dirname(__DIR__), 'run_php.request.received', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'input' => $input,
        'raw_body' => is_string($rawBody) ? $rawBody : null,
    ]);

    $workflow = new ShipmentWorkflow(dirname(__DIR__));
    $result = $workflow->run($input);

    $statusCode = ($result['status'] ?? '') === 'error' ? 422 : 200;
    http_response_code($statusCode);

    RequestLogger::log(dirname(__DIR__), 'run_php.request.completed', [
        'status_code' => $statusCode,
        'result' => $result,
    ]);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);

    RequestLogger::log(dirname(__DIR__), 'run_php.request.failed', [
        'message' => $exception->getMessage(),
        'type' => $exception::class,
        'trace' => $exception->getTraceAsString(),
    ]);

    echo json_encode([
        'status' => 'error',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
