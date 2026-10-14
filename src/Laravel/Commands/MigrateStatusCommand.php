<?php

declare(strict_types=1);

namespace EsOrm\Laravel\Commands;

use EsOrm\Laravel\EsManager;
use Illuminate\Console\Command;

/**
 * `php artisan es:migrate:status` — show the status of every index migration.
 */
final class MigrateStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'es:migrate:status';

    /**
     * @var string
     */
    protected $description = 'Show the status of each Elasticsearch index migration';

    public function handle(EsManager $manager): int
    {
        $rows = array_map(
            static fn (array $status): array => [
                $status['name'],
                $status['ran'] ? 'Yes' : 'No',
                $status['ran'] ? (string) $status['batch'] : '-',
            ],
            $manager->migrator()->status(),
        );

        $this->table(['Migration', 'Ran', 'Batch'], $rows);

        return self::SUCCESS;
    }
}
