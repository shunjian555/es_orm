<?php

declare(strict_types=1);

namespace EsOrm\Migration;

use EsOrm\Exceptions\IndexException;
use JsonException;

/**
 * Persists which index migrations have been run.
 *
 * The core package is framework agnostic, so the state is stored in a small
 * JSON file (`{"ran": {"migration_name": batch}}`) instead of a database table.
 */
final class MigrationRepository
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Every ran migration, keyed by name, with its batch number.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if (is_string($contents) === false || $contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $ran = is_array($decoded) ? ($decoded['ran'] ?? null) : null;

        if (!is_array($ran)) {
            return [];
        }

        $result = [];

        foreach ($ran as $name => $batch) {
            if (is_string($name) && is_numeric($batch)) {
                $result[$name] = (int) $batch;
            }
        }

        return $result;
    }

    /**
     * The names of every ran migration, in insertion order.
     *
     * @return list<string>
     */
    public function ran(): array
    {
        return array_keys($this->all());
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /**
     * The batch number of a ran migration, or null when it has not run.
     */
    public function getBatch(string $name): ?int
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * The highest batch number recorded so far (0 when nothing has run).
     */
    public function lastBatch(): int
    {
        $all = $this->all();

        return $all === [] ? 0 : max($all);
    }

    public function record(string $name, int $batch): void
    {
        $all = $this->all();
        $all[$name] = $batch;

        $this->persist($all);
    }

    public function remove(string $name): void
    {
        $all = $this->all();
        unset($all[$name]);

        $this->persist($all);
    }

    public function flush(): void
    {
        $this->persist([]);
    }

    /**
     * @param array<string, int> $ran
     */
    private function persist(array $ran): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        $json = json_encode(
            ['ran' => $ran],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        if (file_put_contents($this->path, $json . "\n") === false) {
            throw new IndexException(sprintf('Unable to write the migration repository at [%s].', $this->path));
        }
    }
}
