<?php

declare(strict_types=1);

namespace Mrynarzewski\InpostApi;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Throwable;

final class ShipmentWorkflow
{
    private const BASE_URI = 'https://sandbox-api-shipx-pl.easypack24.net';

    /** @var array<string, string> */
    private array $env = [];

    private bool $envLoaded = false;

    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function run(array $input = []): array
    {
        $mode = ($input['mode'] ?? 'simulate') === 'live' ? 'live' : 'simulate';

        try {
            $payload = $this->buildInputPayload($input);
        } catch (RuntimeException $exception) {
            return $this->buildValidationErrorResult($mode, $input, $exception);
        }

        $this->log('shipment_workflow.run.started', [
            'mode' => $mode,
            'input' => $input,
            'payload' => $payload,
        ]);

        if ($mode === 'simulate') {
            $result = $this->runSimulation($payload);
            $this->log('shipment_workflow.run.completed', [
                'mode' => $mode,
                'status' => $result['status'] ?? null,
                'artifacts' => $result['artifacts'] ?? [],
            ]);

            return $result;
        }

        $result = $this->runLive($payload);
        $this->log('shipment_workflow.run.completed', [
            'mode' => $mode,
            'status' => $result['status'] ?? null,
            'artifacts' => $result['artifacts'] ?? [],
            'error' => $result['error'] ?? null,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPresentationData(): array
    {
        return [
            'project' => [
                'name' => 'InpostApi',
                'subtitle' => 'Integracja z InPost ShipX — tworzenie przesyłki i zamówienie kuriera',
                'summary' => 'Pojedynczy skrypt PHP przekształcony w czytelny przepływ integracyjny: od budowania payloadu przez polling statusu aż po zlecenie odbioru.',
            ],
            'highlights' => [
                [
                    'title' => 'Trzy kroki, jeden przepływ',
                    'copy' => 'Najpierw tworzenie przesyłki kurierskiej, potem polling statusu, na końcu zlecenie odbioru — każdy etap widoczny osobno.',
                ],
                [
                    'title' => 'Sandbox InPost ShipX',
                    'copy' => 'Formularz wysyła prawdziwe żądania do środowiska sandbox InPost. Token konfigurowany przez zmienną środowiskową.',
                ],
                [
                    'title' => 'Payload i odpowiedź w jednym miejscu',
                    'copy' => 'Każdy krok pokazuje kształt danych — żądanie, zależności między etapami i decyzje integracyjne.',
                ],
            ],
            'sequence' => [
                [
                    'step' => '01',
                    'title' => 'Budowa payloadu przesyłki',
                    'copy' => 'Dane adresata, nadawcy, gabaryty, masa i typ usługi są składane w żądanie zgodne ze specyfikacją ShipX.',
                ],
                [
                    'step' => '02',
                    'title' => 'Polling statusu',
                    'copy' => 'Po utworzeniu przesyłki integracja czeka, aż status przejdzie do confirmed, zamiast od razu składać kolejne żądanie.',
                ],
                [
                    'step' => '03',
                    'title' => 'Zamówienie kuriera',
                    'copy' => 'Dopiero po potwierdzeniu przesyłki tworzone jest zlecenie odbioru z danymi punktu nadania.',
                ],
            ],
            'metrics' => [
                ['label' => 'Wywołania API', 'value' => '3+'],
                ['label' => 'Środowisko', 'value' => 'Sandbox'],
                ['label' => 'Stack', 'value' => 'PHP + Guzzle'],
            ],
            'defaults' => $this->buildInputPayload([]),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function runSimulation(array $input): array
    {
        $shipmentId = 'shp_' . substr(hash('sha256', json_encode($input)), 0, 10);
        $dispatchId = 'dsp_' . substr(hash('sha256', $shipmentId), 0, 10);

        $this->log('shipment_workflow.simulation.request', [
            'shipment' => [
                'method' => 'POST',
                'path' => '/v1/organizations/' . $input['organization_id'] . '/shipments',
                'body' => $input['shipment'],
            ],
            'dispatch' => [
                'method' => 'POST',
                'path' => '/v1/organizations/' . $input['organization_id'] . '/dispatch_orders',
                'body' => $input['dispatch'],
            ],
        ]);

        $result = [
            'mode' => 'simulate',
            'status' => 'success',
            'meta' => [
                'base_uri' => self::BASE_URI,
                'organization_id' => $input['organization_id'],
                'service' => $input['shipment']['service'],
            ],
            'timeline' => [
                [
                    'title' => 'Shipment request prepared',
                    'state' => 'ready',
                    'detail' => 'Payload zostal zbudowany na podstawie danych nadawcy, odbiorcy i gabarytow.',
                    'request' => [
                        'method' => 'POST',
                        'path' => '/v1/organizations/' . $input['organization_id'] . '/shipments',
                        'body' => $input['shipment'],
                    ],
                ],
                [
                    'title' => 'Shipment created',
                    'state' => 'success',
                    'detail' => 'ShipX przyjal zlecenie i zwrocil identyfikator przesylki.',
                    'response' => [
                        'id' => $shipmentId,
                        'status' => 'created',
                        'service' => $input['shipment']['service'],
                    ],
                ],
                [
                    'title' => 'Shipment confirmed',
                    'state' => 'success',
                    'detail' => 'Integracja odczekala na status confirmed przed przejsciem dalej.',
                    'response' => [
                        'id' => $shipmentId,
                        'status' => 'confirmed',
                    ],
                ],
                [
                    'title' => 'Courier ordered',
                    'state' => 'success',
                    'detail' => 'Po potwierdzeniu shipmentu zlozono dispatch order do odbioru przez kuriera.',
                    'request' => [
                        'method' => 'POST',
                        'path' => '/v1/organizations/' . $input['organization_id'] . '/dispatch_orders',
                        'body' => $input['dispatch'],
                    ],
                    'response' => [
                        'id' => $dispatchId,
                        'status' => 'created',
                        'shipments' => [$shipmentId],
                    ],
                ],
            ],
            'artifacts' => [
                'shipment_id' => $shipmentId,
                'dispatch_order_id' => $dispatchId,
            ],
        ];

        $this->log('shipment_workflow.simulation.completed', [
            'shipment_id' => $shipmentId,
            'dispatch_order_id' => $dispatchId,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function runLive(array $input): array
    {
        $this->loadEnv();

        $apiToken = $this->env['PACZKOMATY_INPOST_APITOKEN'] ?? '';
        if ($apiToken === '') {
            throw new RuntimeException('Brak PACZKOMATY_INPOST_APITOKEN. Dla trybu live uzupelnij plik .env.');
        }

        $client = new Client([
            'base_uri' => self::BASE_URI,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $timeline = [];

        try {
            $timeline[] = [
                'title' => 'Shipment request prepared',
                'state' => 'ready',
                'detail' => 'Payload zostal zbudowany i wyslany do ShipX.',
                'request' => [
                    'method' => 'POST',
                    'path' => '/v1/organizations/' . $input['organization_id'] . '/shipments',
                    'body' => $input['shipment'],
                ],
            ];

            $this->log('shipment_workflow.live.shipment.request', [
                'method' => 'POST',
                'path' => '/v1/organizations/' . $input['organization_id'] . '/shipments',
                'body' => $input['shipment'],
            ]);

            $shipmentResponse = $client->post('/v1/organizations/' . $input['organization_id'] . '/shipments', [
                'json' => $input['shipment'],
            ]);

            /** @var array<string, mixed> $shipment */
            $shipment = json_decode((string) $shipmentResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->log('shipment_workflow.live.shipment.response', [
                'path' => '/v1/organizations/' . $input['organization_id'] . '/shipments',
                'body' => $shipment
            ]);
            $shipmentId = $shipment['id'] ?? null;

            if (!is_numeric($shipmentId) || $shipmentId === '') {
                throw new RuntimeException('Brak shipment ID w odpowiedzi API.');
            }

            $this->log('shipment_workflow.live.shipment.response', [
                'shipment_id' => $shipmentId,
                'response' => $shipment,
            ]);

            $timeline[] = [
                'title' => 'Shipment created',
                'state' => 'success',
                'detail' => 'API zwrocilo identyfikator przesylki.',
                'response' => $shipment,
            ];

            $confirmedShipment = $shipment;
            $attempt = 0;

            do {
                ++$attempt;
                sleep(2);

                $this->log('shipment_workflow.live.shipment.poll.request', [
                    'attempt' => $attempt,
                    'method' => 'GET',
                    'path' => '/v1/shipments/' . $shipmentId,
                ]);

                $statusResponse = $client->get('/v1/shipments/' . $shipmentId);
                /** @var array<string, mixed> $confirmedShipment */
                $confirmedShipment = json_decode((string) $statusResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $status = $confirmedShipment['status'] ?? 'unknown';

                $this->log('shipment_workflow.live.shipment.poll.response', [
                    'attempt' => $attempt,
                    'shipment_id' => $shipmentId,
                    'status' => $status,
                    'response' => $confirmedShipment,
                ]);

                $timeline[] = [
                    'title' => 'Shipment poll #' . $attempt,
                    'state' => $status === 'confirmed' ? 'success' : 'pending',
                    'detail' => 'Aktualny status shipmentu: ' . $status,
                    'response' => $confirmedShipment,
                ];
            } while (($confirmedShipment['status'] ?? null) !== 'confirmed' && $attempt < 8);

            if (($confirmedShipment['status'] ?? null) !== 'confirmed') {
                throw new RuntimeException('Shipment nie osiagnal statusu confirmed w oczekiwanym czasie.');
            }

            $dispatchPayload = $input['dispatch'];
            $dispatchPayload['shipments'] = [$shipmentId];

            $this->log('shipment_workflow.live.dispatch.request', [
                'method' => 'POST',
                'path' => '/v1/organizations/' . $input['organization_id'] . '/dispatch_orders',
                'body' => $dispatchPayload,
                'shipment_id' => $shipmentId,
            ]);

            $dispatchResponse = $client->post('/v1/organizations/' . $input['organization_id'] . '/dispatch_orders', [
                'json' => $dispatchPayload,
            ]);

            /** @var array<string, mixed> $dispatch */
            $dispatch = json_decode((string) $dispatchResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $this->log('shipment_workflow.live.dispatch.response', [
                'shipment_id' => $shipmentId,
                'dispatch_order_id' => $dispatch['id'] ?? null,
                'response' => $dispatch,
            ]);

            $timeline[] = [
                'title' => 'Courier ordered',
                'state' => 'success',
                'detail' => 'Dispatch order zostal poprawnie utworzony.',
                'request' => [
                    'method' => 'POST',
                    'path' => '/v1/organizations/' . $input['organization_id'] . '/dispatch_orders',
                    'body' => $dispatchPayload,
                ],
                'response' => $dispatch,
            ];

            return [
                'mode' => 'live',
                'status' => 'success',
                'meta' => [
                    'base_uri' => self::BASE_URI,
                    'organization_id' => $input['organization_id'],
                    'service' => $input['shipment']['service'],
                ],
                'timeline' => $timeline,
                'artifacts' => [
                    'shipment_id' => $shipmentId,
                    'dispatch_order_id' => $dispatch['id'] ?? null,
                ],
            ];
        } catch (RequestException $exception) {
            return $this->buildErrorResult($exception, $timeline);
        } catch (GuzzleException|RuntimeException|Throwable $exception) {
            return $this->buildErrorResult($exception, $timeline);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function buildInputPayload(array $input): array
    {
        $organizationId = (string) ($input['organization_id'] ?? $this->getEnvValue('PACZKOMATY_INPOST_ORGANIZATIONID', '5269'));
        $receiverPostCode = $this->normalizePolishPostCode(
            (string) ($input['receiver_post_code'] ?? '62-200'),
            'Kod pocztowy odbiorcy'
        );
        $senderPostCode = $this->normalizePolishPostCode(
            (string) ($input['sender_post_code'] ?? '25-437'),
            'Kod pocztowy nadawcy'
        );
        $dispatchPostCode = $this->normalizePolishPostCode(
            (string) ($input['dispatch_post_code'] ?? '31-209'),
            'Kod pocztowy odbioru kuriera'
        );

        $receiver = [
            'company_name' => (string) ($input['receiver_company'] ?? 'Focus Garden Client'),
            'first_name' => (string) ($input['receiver_first_name'] ?? 'Jan'),
            'last_name' => (string) ($input['receiver_last_name'] ?? 'Kowalski'),
            'email' => (string) ($input['receiver_email'] ?? 'jan.kowalski@example.com'),
            'phone' => (string) ($input['receiver_phone'] ?? '600700800'),
            'name' => trim((string) (($input['receiver_first_name'] ?? 'Jan') . ' ' . ($input['receiver_last_name'] ?? 'Kowalski'))),
            'address' => [
                'street' => (string) ($input['receiver_street'] ?? 'Fabryczna'),
                'building_number' => (string) ($input['receiver_building'] ?? '2'),
                'city' => (string) ($input['receiver_city'] ?? 'Gniezno'),
                'post_code' => $receiverPostCode,
                'country_code' => 'PL',
            ],
        ];

        $sender = [
            'name' => (string) ($input['sender_name'] ?? 'Marek Rynarzewski'),
            'company_name' => (string) ($input['sender_company'] ?? 'Focus Garden Studio'),
            'first_name' => null,
            'last_name' => null,
            'email' => (string) ($input['sender_email'] ?? 'hello@example.com'),
            'phone' => (string) ($input['sender_phone'] ?? '500400300'),
            'address' => [
                'street' => (string) ($input['sender_street'] ?? 'Na Stoku'),
                'building_number' => (string) ($input['sender_building'] ?? '18'),
                'city' => (string) ($input['sender_city'] ?? 'Kielce'),
                'post_code' => $senderPostCode,
                'country_code' => 'PL',
            ],
        ];

        $shipment = [
            'receiver' => $receiver,
            'sender' => $sender,
            'parcels' => [
                [
                    'id' => 'showcase-package',
                    'dimensions' => [
                        'length' => (string) ($input['parcel_length'] ?? '80'),
                        'width' => (string) ($input['parcel_width'] ?? '360'),
                        'height' => (string) ($input['parcel_height'] ?? '640'),
                        'unit' => 'mm',
                    ],
                    'weight' => [
                        'amount' => (string) ($input['parcel_weight'] ?? '25'),
                        'unit' => 'kg',
                    ],
                    'is_non_standard' => false,
                ],
            ],
            'service' => 'inpost_courier_standard',
        ];

        return [
            'organization_id' => $organizationId,
            'shipment' => $shipment,
            'dispatch' => [
                'shipments' => [],
                'comment' => (string) ($input['dispatch_comment'] ?? 'Odbior paczki przygotowany przez demo showcase'),
                'name' => (string) ($input['dispatch_name'] ?? 'Focus Garden Dispatch Point'),
                'phone' => (string) ($input['dispatch_phone'] ?? '505404202'),
                'email' => (string) ($input['dispatch_email'] ?? 'dispatch@example.com'),
                'address' => [
                    'street' => (string) ($input['dispatch_street'] ?? 'Malborska'),
                    'building_number' => (string) ($input['dispatch_building'] ?? '130'),
                    'city' => (string) ($input['dispatch_city'] ?? 'Krakow'),
                    'post_code' => $dispatchPostCode,
                    'country_code' => 'PL',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function buildValidationErrorResult(string $mode, array $input, RuntimeException $exception): array
    {
        $message = $exception->getMessage();
        $result = [
            'mode' => $mode,
            'status' => 'error',
            'timeline' => [
                [
                    'title' => 'Validation failed',
                    'state' => 'error',
                    'detail' => 'Payload nie zostal wyslany do ShipX, bo dane adresowe wymagaja poprawy.',
                    'response' => ['message' => $message],
                ],
            ],
            'error' => $message,
        ];

        $this->log('shipment_workflow.run.validation_failed', [
            'mode' => $mode,
            'input' => $input,
            'message' => $message,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<string, mixed>
     */
    private function buildErrorResult(Throwable $exception, array $timeline): array
    {
        $message = $exception->getMessage();

        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $message = (string) $exception->getResponse()->getBody();
        }

        $timeline[] = [
            'title' => 'Workflow failed',
            'state' => 'error',
            'detail' => 'Integracja przerwala flow po bledzie API lub konfiguracji.',
            'response' => ['message' => $message],
        ];

        $this->log('shipment_workflow.failed', [
            'message' => $message,
            'type' => $exception::class,
            'timeline' => $timeline,
        ]);

        return [
            'mode' => 'live',
            'status' => 'error',
            'timeline' => $timeline,
            'error' => $message,
        ];
    }

    private function loadEnv(): void
    {
        if ($this->envLoaded) {
            return;
        }

        if (is_file($this->projectRoot . '/.env')) {
            Dotenv::createImmutable($this->projectRoot)->safeLoad();
        }

        $this->env = array_merge($_SERVER, $_ENV);
        $this->envLoaded = true;
    }

    private function getEnvValue(string $key, string $fallback): string
    {
        $this->loadEnv();

        $value = $this->env[$key] ?? '';
        if (!is_string($value) || $value === '') {
            return $fallback;
        }

        return $value;
    }

    private function normalizePolishPostCode(string $value, string $fieldLabel): string
    {
        $rawValue = trim($value);
        $normalized = preg_replace('/\s+/', '', $rawValue);

        if (!is_string($normalized)) {
            throw new RuntimeException($fieldLabel . ' ma nieprawidlowy format.');
        }

        if (preg_match('/^\d{5}$/', $normalized) === 1) {
            $normalized = substr($normalized, 0, 2) . '-' . substr($normalized, 2);
        }

        if (preg_match('/^\d{2}-\d{3}$/', $normalized) !== 1) {
            throw new RuntimeException(sprintf(
                '%s "%s" jest nieprawidlowy. Uzyj formatu 00-000.',
                $fieldLabel,
                $rawValue === '' ? '(pusty)' : $rawValue
            ));
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $event, array $context = []): void
    {
        RequestLogger::log($this->projectRoot, $event, $context);
    }
}
