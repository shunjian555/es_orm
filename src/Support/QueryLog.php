<?php

declare(strict_types=1);

namespace EsOrm\Support;

/**
 * Collects the Elasticsearch requests executed during the current process.
 *
 * Enabled by default (`logging.enabled` config key) and bounded by
 * `logging.max_entries` so long running workers cannot leak memory.
 */
final class QueryLog
{
    /**
     * @var list<QueryLogEntry>
     */
    private array $entries = [];

    private bool $enabled = true;

    public function __construct(private int $maxEntries = 100)
    {
        $this->maxEntries = max(1, $maxEntries);
    }

    public function record(QueryLogEntry $entry): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->entries[] = $entry;

        if (count($this->entries) > $this->maxEntries) {
            $this->entries = array_slice($this->entries, -$this->maxEntries);
        }
    }

    /**
     * @return list<QueryLogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function last(): ?QueryLogEntry
    {
        return $this->entries === [] ? null : $this->entries[array_key_last($this->entries)];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function totalDuration(): float
    {
        return array_sum(array_map(static fn (QueryLogEntry $entry): float => $entry->duration, $this->entries));
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function enable(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
