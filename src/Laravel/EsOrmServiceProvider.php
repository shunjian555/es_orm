<?php

declare(strict_types=1);

namespace EsOrm\Laravel;

use EsOrm\Client\ConnectionManager;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Laravel\Commands\MigrateCommand;
use EsOrm\Laravel\Commands\MigrateRollbackCommand;
use EsOrm\Laravel\Commands\MigrateStatusCommand;
use EsOrm\Model\Model;
use EsOrm\Support\Config;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Elasticsearch ORM services inside a Laravel application.
 *
 * The provider is registered by Composer's package discovery; it binds the
 * connection manager, the {@see EsManager} (behind the `Es` facade) and the
 * Artisan commands, and publishes `config/es_orm.php`.
 *
 * The core package never depends on Laravel — this class is the only bridge.
 */
final class EsOrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . '/config/es_orm.php', 'es_orm');

        $this->app->singleton(ConnectionManagerInterface::class, function (): ConnectionManager {
            $manager = new ConnectionManager(Config::fromArray($this->esConfig()));
            Model::setConnectionManager($manager);

            return $manager;
        });

        $this->app->alias(ConnectionManagerInterface::class, 'es-orm.connection-manager');

        $this->app->singleton(EsManager::class, function (): EsManager {
            $migrations = $this->esConfig()['migrations'] ?? [];
            $migrations = is_array($migrations) ? $migrations : [];
            $path = $migrations['path'] ?? null;
            $repository = $migrations['repository'] ?? null;

            /** @var ConnectionManagerInterface $connections */
            $connections = $this->app->make(ConnectionManagerInterface::class);

            return new EsManager(
                $connections,
                is_string($path) ? $path : 'database/es-migrations',
                is_string($repository) ? $repository : 'es-orm-migrations.json',
            );
        });

        $this->app->alias(EsManager::class, 'es-orm');
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2) . '/config/es_orm.php' => (string) $this->app->configPath('es_orm.php'),
        ], 'es-orm-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommand::class,
                MigrateRollbackCommand::class,
                MigrateStatusCommand::class,
            ]);
        }
    }

    /**
     * The `es-orm` configuration array.
     *
     * @return array<string, mixed>
     */
    private function esConfig(): array
    {
        /** @var ConfigRepository $repository */
        $repository = $this->app->make('config');
        $config = $repository->get('es_orm');

        return is_array($config) ? $config : [];
    }
}
