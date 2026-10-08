<?php

declare(strict_types=1);

final class ProcessService
{
    public function __construct(private ProcessRepository $repository)
    {
    }

    public function normalizeConfig(array $configuration): array
    {
        $fields = [];
        foreach (array_slice(is_array($configuration['fields'] ?? null) ? $configuration['fields'] : [], 0, 250) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $normalized = ProcessNormalizer::normalizeField($field);
            if ($normalized['key'] !== '') {
                $fields[] = $normalized;
            }
        }

        if (!$fields) {
            throw new InvalidArgumentException('Mindestens ein Feld ist erforderlich.');
        }

        $linkTypes = ProcessNormalizer::normalizeValues($configuration['linkTypes'] ?? ['Normal', 'ParentChild']);
        if (!$linkTypes) {
            $linkTypes = ['Normal', 'ParentChild'];
        }

        return [
            'fields' => $fields,
            'linkTypes' => $linkTypes,
        ];
    }

    public function normalizeProcess(array $process): array
    {
        $normalized = ProcessNormalizer::normalizeProcess($process);
        $this->validateProcess($normalized);
        return $normalized;
    }

    public function validateProcess(array $process): void
    {
        $name = trim((string)($process['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Prozessname fehlt.');
        }

        $steps = is_array($process['steps'] ?? null) ? $process['steps'] : [];
        if (!$steps) {
            throw new InvalidArgumentException('Mindestens ein Schritt ist erforderlich.');
        }

        $firstStep = $steps[0];
        $fieldKeys = array_map(static fn($field) => $field['key'] ?? '', $firstStep['fields'] ?? []);
        foreach (['Type', 'Queue', 'Service'] as $requiredKey) {
            if (!in_array($requiredKey, $fieldKeys, true)) {
                throw new InvalidArgumentException('Im ersten Schritt fehlen erforderliche Ticketfelder.');
            }
        }
    }

    public function saveConfig(array $configuration): array
    {
        $data = $this->repository->load();
        $data['configuration'] = $this->normalizeConfig($configuration);
        $this->repository->save($data);
        return $data;
    }

    public function saveProcess(array $process): array
    {
        $data = $this->repository->load();
        $normalized = $this->normalizeProcess($process);
        $updated = false;

        foreach ($data['processes'] as $index => $existing) {
            if (($existing['id'] ?? '') === ($normalized['id'] ?? '')) {
                $data['processes'][$index] = $normalized;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $data['processes'][] = $normalized;
        }

        $data['processes'] = array_values($data['processes']);
        $this->repository->save($data);
        return $normalized;
    }

    public function deleteProcess(string $id): void
    {
        $data = $this->repository->load();
        $data['processes'] = array_values(array_filter(
            $data['processes'],
            static fn(array $process): bool => ($process['id'] ?? '') !== $id
        ));
        $this->repository->save($data);
    }

    public function importData(array $incoming): array
    {
        $normalized = ProcessNormalizer::normalizeStorage($incoming);
        $data = $this->repository->load();
        $data['configuration'] = $normalized['configuration'];
        $data['processes'] = array_merge($data['processes'], $normalized['processes']);
        $this->repository->save($data);
        return $data;
    }
}
