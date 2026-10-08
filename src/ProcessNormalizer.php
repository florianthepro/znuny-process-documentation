<?php

declare(strict_types=1);

final class ProcessNormalizer
{
    public static function cleanString(mixed $value, int $max = 10000): string
    {
        $text = trim(is_scalar($value) ? (string) $value : '');
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }
        return substr($text, 0, $max);
    }

    public static function normalizeValues(mixed $value): array
    {
        if (is_string($value)) {
            $items = preg_split('/\R/u', $value) ?: [];
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            return [];
        }

        $result = [];
        foreach (array_slice($items, 0, 200) as $item) {
            $text = self::cleanString($item, 300);
            if ($text !== '' && !in_array($text, $result, true)) {
                $result[] = $text;
            }
        }
        return $result;
    }

    public static function defaultConfig(): array
    {
        return [
            'schemaVersion' => 3,
            'configuration' => [
                'fields' => [
                    [
                        'id' => 'field-type',
                        'key' => 'Type',
                        'label' => 'Type',
                        'type' => 'select',
                        'values' => ['Change', 'Compliance', 'Incident', 'Problem', 'ServiceRequest', 'SPAM', 'Unclassified'],
                        'defaultValue' => '',
                        'required' => true,
                    ],
                    [
                        'id' => 'field-queue',
                        'key' => 'Queue',
                        'label' => 'Queue',
                        'type' => 'select',
                        'values' => ['Consulting', 'RMA', 'SPAM', 'Support'],
                        'defaultValue' => '',
                        'required' => true,
                    ],
                    [
                        'id' => 'field-service',
                        'key' => 'Service',
                        'label' => 'Service',
                        'type' => 'select',
                        'values' => ['Basic Support', 'Extra Support', 'Internal', 'Managed Service'],
                        'defaultValue' => '',
                        'required' => true,
                    ],
                ],
                'linkTypes' => ['Normal', 'ParentChild'],
            ],
            'processes' => [],
        ];
    }

    public static function normalizeField(array $field): array
    {
        $type = self::cleanString($field['type'] ?? 'text', 30);
        if (!in_array($type, ['text', 'select', 'number'], true)) {
            $type = 'text';
        }

        return [
            'id' => self::cleanString($field['id'] ?? '', 100) ?: 'field-' . bin2hex(random_bytes(6)),
            'key' => self::cleanString($field['key'] ?? $field['name'] ?? '', 120),
            'label' => self::cleanString($field['label'] ?? $field['name'] ?? $field['key'] ?? '', 160),
            'type' => $type,
            'values' => self::normalizeValues($field['values'] ?? $field['options'] ?? []),
            'defaultValue' => self::cleanString($field['defaultValue'] ?? $field['default'] ?? '', 5000),
            'required' => (bool)($field['required'] ?? false),
        ];
    }

    public static function normalizeVariable(array $variable): array
    {
        $type = self::cleanString($variable['type'] ?? 'text', 30);
        if (!in_array($type, ['text', 'select', 'number', 'random'], true)) {
            $type = 'text';
        }

        return [
            'id' => self::cleanString($variable['id'] ?? '', 100) ?: 'variable-' . bin2hex(random_bytes(6)),
            'name' => self::cleanString($variable['name'] ?? '', 160),
            'type' => $type,
            'values' => self::normalizeValues($variable['values'] ?? []),
            'prefill' => self::cleanString($variable['prefill'] ?? $variable['default'] ?? '', 5000),
            'minimum' => isset($variable['minimum']) && $variable['minimum'] !== '' ? (float) $variable['minimum'] : null,
            'maximum' => isset($variable['maximum']) && $variable['maximum'] !== '' ? (float) $variable['maximum'] : null,
            'hidden' => (bool)($variable['hidden'] ?? false),
            'marked' => (bool)($variable['marked'] ?? false),
        ];
    }

    public static function normalizeStep(array $step): array
    {
        $fields = [];
        foreach (array_slice(is_array($step['fields'] ?? null) ? $step['fields'] : [], 0, 250) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = self::cleanString($field['key'] ?? $field['field'] ?? '', 120);
            if ($key === '') {
                continue;
            }
            $fields[] = [
                'id' => self::cleanString($field['id'] ?? '', 100) ?: 'step-field-' . bin2hex(random_bytes(6)),
                'key' => $key,
                'value' => self::cleanString($field['value'] ?? '', 5000),
                'enabled' => (bool)($field['enabled'] ?? true),
            ];
        }

        $meta = is_array($step['meta'] ?? null) ? $step['meta'] : [];
        $condition = is_array($meta['condition'] ?? null) ? $meta['condition'] : [];

        return [
            'id' => self::cleanString($step['id'] ?? '', 100) ?: 'step-' . bin2hex(random_bytes(6)),
            'action' => self::cleanString($step['action'] ?? 'note', 50),
            'subject' => self::cleanString($step['subject'] ?? $step['title'] ?? '', 5000),
            'body' => self::cleanString($step['body'] ?? $step['text'] ?? '', 30000),
            'fields' => $fields,
            'links' => [],
            'meta' => [
                'system' => self::cleanString($meta['system'] ?? '', 5000),
                'url' => self::cleanString($meta['url'] ?? '', 5000),
                'address' => self::cleanString($meta['address'] ?? '', 5000),
                'tasks' => [],
                'condition' => [
                    'variable' => self::cleanString($condition['variable'] ?? '', 160),
                    'operator' => self::cleanString($condition['operator'] ?? 'always', 40),
                    'value' => self::cleanString($condition['value'] ?? '', 5000),
                ],
            ],
        ];
    }

    public static function normalizeProcess(array $process): array
    {
        $variables = [];
        foreach (array_slice(is_array($process['variables'] ?? null) ? $process['variables'] : [], 0, 150) as $rawVariable) {
            if (!is_array($rawVariable)) {
                continue;
            }
            $variable = self::normalizeVariable($rawVariable);
            if ($variable['name'] !== '') {
                $variables[] = $variable;
            }
        }

        $steps = [];
        foreach (array_slice(is_array($process['steps'] ?? null) ? $process['steps'] : [], 0, 150) as $step) {
            if (!is_array($step)) {
                continue;
            }
            $steps[] = self::normalizeStep($step);
        }

        if (!$steps) {
            $steps[] = [
                'id' => 'step-' . bin2hex(random_bytes(6)),
                'action' => 'ticket-create',
                'subject' => '',
                'body' => '',
                'fields' => [],
                'links' => [],
                'meta' => [
                    'system' => '',
                    'url' => '',
                    'address' => '',
                    'tasks' => [],
                    'condition' => ['variable' => '', 'operator' => 'always', 'value' => ''],
                ],
            ];
        }

        return [
            'id' => self::cleanString($process['id'] ?? '', 100) ?: 'process-' . bin2hex(random_bytes(6)),
            'name' => self::cleanString($process['name'] ?? 'Unbenannter Prozess', 160),
            'description' => self::cleanString($process['description'] ?? '', 1000),
            'yamlName' => self::cleanString($process['yamlName'] ?? '', 200),
            'published' => (bool)($process['published'] ?? true),
            'customerMarks' => is_array($process['customerMarks'] ?? null) ? array_values($process['customerMarks']) : [],
            'variables' => $variables,
            'steps' => $steps,
            'updatedAt' => self::cleanString($process['updatedAt'] ?? '', 50) ?: date(DATE_ATOM),
        ];
    }

    public static function normalizeStorage(array $data): array
    {
        $default = self::defaultConfig();
        $config = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];
        $fields = [];
        foreach (array_slice(is_array($config['fields'] ?? null) ? $config['fields'] : [], 0, 250) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $normalized = self::normalizeField($field);
            if ($normalized['key'] !== '') {
                $fields[] = $normalized;
            }
        }

        $linkTypes = self::normalizeValues($config['linkTypes'] ?? ['Normal', 'ParentChild']);
        if (!$linkTypes) {
            $linkTypes = ['Normal', 'ParentChild'];
        }

        $processes = [];
        foreach (array_slice(is_array($data['processes'] ?? null) ? $data['processes'] : [], 0, 500) as $rawProcess) {
            if (!is_array($rawProcess)) {
                continue;
            }
            $processes[] = self::normalizeProcess($rawProcess);
        }

        return [
            'schemaVersion' => 3,
            'configuration' => [
                'fields' => $fields ?: $default['configuration']['fields'],
                'linkTypes' => $linkTypes,
            ],
            'processes' => $processes,
        ];
    }
}

