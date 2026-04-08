<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/ShipmentWorkflow.php';

use FocusGarden\ShipmentWorkflow;

$mode = in_array('--live', $argv, true) ? 'live' : 'simulate';

$workflow = new ShipmentWorkflow(__DIR__);
$result = $workflow->run(['mode' => $mode]);

echo 'Mode: ' . $result['mode'] . PHP_EOL;
echo 'Status: ' . $result['status'] . PHP_EOL . PHP_EOL;

foreach ($result['timeline'] as $step) {
    echo '[' . strtoupper((string) $step['state']) . '] ' . $step['title'] . PHP_EOL;
    echo $step['detail'] . PHP_EOL;

    if (isset($step['request'])) {
        echo 'Request:' . PHP_EOL;
        echo json_encode($step['request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    if (isset($step['response'])) {
        echo 'Response:' . PHP_EOL;
        echo json_encode($step['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    echo str_repeat('-', 72) . PHP_EOL;
}

if (($result['status'] ?? '') === 'error') {
    exit(1);
}
