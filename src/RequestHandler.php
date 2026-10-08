<?php

declare(strict_types=1);

final class RequestHandler
{
    public function __construct(
        private ProcessRepository $repository,
        private ProcessService $service
    ) {
    }

    public function handle(array $payload): array
    {
        $op = strtolower((string)($payload['op'] ?? ''));

        switch ($op) {
            case 'save-config':
                if (!is_array($payload['configuration'] ?? null)) {
                    throw new InvalidArgumentException('Konfiguration fehlt.');
                }
                $this->service->saveConfig($payload['configuration']);
                return ['ok' => true];

            case 'save-process':
                if (!is_array($payload['process'] ?? null)) {
                    throw new InvalidArgumentException('Prozess fehlt.');
                }
                $process = $this->service->saveProcess($payload['process']);
                return ['ok' => true, 'id' => $process['id'] ?? ''];

            case 'delete-process':
                $id = trim((string)($payload['id'] ?? ''));
                if ($id === '') {
                    throw new InvalidArgumentException('Prozess-ID fehlt.');
                }
                $this->service->deleteProcess($id);
                return ['ok' => true];

            case 'import-json':
                if (!is_array($payload['data'] ?? null)) {
                    throw new InvalidArgumentException('Importdaten fehlen.');
                }
                $data = $this->service->importData($payload['data']);
                return ['ok' => true, 'count' => count($data['processes'])];

            default:
                throw new InvalidArgumentException('Unbekannte Aktion.');
        }
    }
}
