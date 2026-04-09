<?php

declare(strict_types=1);

namespace Mrynarzewski\InpostApi;

final class RequestLogger
{
    private const LOG_FILE = '/var/logs/focus-garden-requests.log';

    /**
     * @param array<string, mixed> $context
     */
    public static function log(string $projectRoot, string $event, array $context = []): void
    {
        try {
            $logFile = rtrim($projectRoot, '/') . self::LOG_FILE;
            $logDir = dirname($logFile);

            if (!is_dir($logDir) && !@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
                return;
            }

            $entry = [
                'timestamp' => gmdate('c'),
                'event' => $event,
                'context' => self::normalize($context),
            ];

            $payload = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = json_encode([
                    'timestamp' => gmdate('c'),
                    'event' => 'logger.encoding_failed',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_string($payload)) {
                @file_put_contents($logFile, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[(string) $key] = self::normalize($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            return self::normalize(get_object_vars($value));
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }
}
