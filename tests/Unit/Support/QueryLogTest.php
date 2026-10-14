<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\QueryLog;
use EsOrm\Support\QueryLogEntry;
use PHPUnit\Framework\TestCase;

final class QueryLogTest extends TestCase
{
    private function entry(string $operation = 'search', float $duration = 1.0): QueryLogEntry
    {
        return new QueryLogEntry(
            operation: $operation,
            method: 'POST',
            endpoint: '/_search',
            duration: $duration,
        );
    }

    public function test_it_starts_empty_and_enabled(): void
    {
        $log = new QueryLog();

        self::assertSame([], $log->entries());
        self::assertSame(0, $log->count());
        self::assertNull($log->last());
        self::assertTrue($log->isEnabled());
    }

    public function test_it_records_entries_in_order(): void
    {
        $log = new QueryLog();
        $first = $this->entry('search');
        $second = $this->entry('count');

        $log->record($first);
        $log->record($second);

        self::assertSame([$first, $second], $log->entries());
        self::assertSame(2, $log->count());
        self::assertSame($second, $log->last());
    }

    public function test_it_trims_entries_beyond_max_entries(): void
    {
        $log = new QueryLog(2);

        $log->record($this->entry('first'));
        $log->record($this->entry('second'));
        $log->record($this->entry('third'));

        self::assertSame(2, $log->count());
        self::assertSame('second', $log->entries()[0]->operation);
        self::assertSame('third', $log->last()?->operation);
    }

    public function test_it_clamps_max_entries_to_at_least_one(): void
    {
        $log = new QueryLog(0);

        $log->record($this->entry('first'));
        $log->record($this->entry('second'));

        self::assertSame(1, $log->count());
        self::assertSame('second', $log->last()?->operation);
    }

    public function test_it_sums_entry_durations(): void
    {
        $log = new QueryLog();
        $log->record($this->entry('search', 1.5));
        $log->record($this->entry('count', 2.25));

        self::assertSame(3.75, $log->totalDuration());
    }

    public function test_it_clears_all_entries(): void
    {
        $log = new QueryLog();
        $log->record($this->entry());
        $log->clear();

        self::assertSame([], $log->entries());
        self::assertSame(0, $log->count());
    }

    public function test_it_ignores_entries_while_disabled(): void
    {
        $log = new QueryLog();
        $log->enable(false);

        $log->record($this->entry());

        self::assertFalse($log->isEnabled());
        self::assertSame(0, $log->count());
    }

    public function test_it_resumes_recording_when_re_enabled(): void
    {
        $log = new QueryLog();
        $log->enable(false);
        $log->record($this->entry('skipped'));
        $log->enable(true);
        $log->record($this->entry('kept'));

        self::assertSame(1, $log->count());
        self::assertSame('kept', $log->last()?->operation);
    }
}
