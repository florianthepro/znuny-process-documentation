<?php

declare(strict_types=1);

final class ProcessRepository
{
    public function __construct(private string $dataFile)
    {
    }

    public function load(): array
    {
        if (!is_file($this->dataFile)) {
            $default = ProcessNormalizer::defaultConfig();
            $this->save($default);
            return $default;
        }

        $contents = file_get_contents($this->dataFile);
        if ($contents === false) {
            throw new RuntimeException('Datei konnte nicht gelesen werden.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ungültiges JSON-Format.');
        }

        return ProcessNormalizer::normalizeStorage($decoded);
    }

    public function save(array $data): void
    {
        $directory = dirname($this->dataFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Datenverzeichnis konnte nicht erstellt werden.');
            }
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n";

        if (file_put_contents($this->dataFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('Daten konnten nicht gespeichert werden.');
        }
    }
}
