<?php

declare(strict_types=1);

namespace EsOrm\Laravel\Commands;

use EsOrm\Laravel\EsManager;
use Illuminate\Console\Command;

/**
 * `php artisan es:migrate` — run every pending index migration.
 */
final class MigrateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'es:migrate';

    /**
     * @var string
     */
    protected $description = 'Run the pending Elasticsearch index migrations';

    public function handle(EsManager $manager): int
    {
        $migrated = $manager->migrator()->migrate();

        if ($migrated === []) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        foreach ($migrated as $name) {
            $this->info(sprintf('Migrated: %s', $name));
        }

        return self::SUCCESS;
    }
}
