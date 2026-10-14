<?php

declare(strict_types=1);

namespace EsOrm\Laravel\Commands;

use EsOrm\Laravel\EsManager;
use Illuminate\Console\Command;

/**
 * `php artisan es:migrate:rollback` — roll back the last batch(es) of index migrations.
 */
final class MigrateRollbackCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'es:migrate:rollback {--step=1 : The number of batches to roll back}';

    /**
     * @var string
     */
    protected $description = 'Roll back the Elasticsearch index migrations';

    public function handle(EsManager $manager): int
    {
        $step = $this->option('step');
        $steps = is_numeric($step) ? (int) $step : 1;

        $rolledBack = $manager->migrator()->rollback($steps);

        if ($rolledBack === []) {
            $this->info('Nothing to roll back.');

            return self::SUCCESS;
        }

        foreach ($rolledBack as $name) {
            $this->info(sprintf('Rolled back: %s', $name));
        }

        return self::SUCCESS;
    }
}
