<?php

declare(strict_types=1);

namespace EsOrm\Migration;

use EsOrm\Exceptions\IndexException;
use EsOrm\Index\IndexManager;

/**
 * Discovers and runs {@see IndexMigration} classes.
 *
 * The runner is framework agnostic: a Laravel application wraps it in Artisan
 * commands (`es:migrate`, `es:migrate:rollback`, `es:migrate:status`) while a
 * plain PHP project can call it directly.
 *
 * Migration files live in `$path` and must declare a class extending
 * {@see IndexMigration}. Files are ordered by their filename, so a timestamp
 * prefix (`2026_10_14_000000_create_articles_index.php`) gives a deterministic
 * order, exactly like Laravel migrations.
 */
final class Migrator
{
    private ?MigrationRepository $repository = null;

    /**
     * @var array<string, IndexMigration>
     */
    private array $resolved = [];

    public function __construct(
        private readonly IndexManager $manager,
        private readonly string $path,
        private readonly string $repositoryFile = 'es-orm-migrations.json',
    ) {
    }

    /**
     * The migration state repository.
     */
    public function repository(): MigrationRepository
    {
        return $this->repository ??= new MigrationRepository(
            rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . $this->repositoryFile,
        );
    }

    /**
     * Run every pending migration.
     *
     * @return list<string> The names of the migrations that ran.
     */
    public function migrate(): array
    {
        $batch = $this->repository()->lastBatch() + 1;
        $migrated = [];

        foreach ($this->migrations() as $name => $file) {
            if ($this->repository()->has($name)) {
                continue;
            }

            $this->resolve($file)->withManager($this->manager)->up();
            $this->repository()->record($name, $batch);
            $migrated[] = $name;
        }

        return $migrated;
    }

    /**
     * Roll back the given number of batches.
     *
     * @return list<string> The names of the migrations that were rolled back.
     */
    public function rollback(int $steps = 1): array
    {
        if ($steps < 1) {
            return [];
        }

        $all = $this->repository()->all();

        if ($all === []) {
            return [];
        }

        $targetBatch = max($all) - $steps + 1;

        $candidates = array_filter($all, static fn (int $batch): bool => $batch >= $targetBatch);

        // Roll back in the reverse order the migrations were declared.
        $ordered = array_values(array_intersect(
            array_reverse(array_keys($this->migrations())),
            array_keys($candidates),
        ));

        $rolledBack = [];
        $files = $this->migrations();

        foreach ($ordered as $name) {
            $file = $files[$name] ?? null;

            if ($file === null) {
                continue;
            }

            $this->resolve($file)->withManager($this->manager)->down();
            $this->repository()->remove($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * The status of every discovered migration.
     *
     * @return list<array{name: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $status = [];

        foreach (array_keys($this->migrations()) as $name) {
            $status[] = [
                'name' => $name,
                'ran' => $this->repository()->has($name),
                'batch' => $this->repository()->getBatch($name),
            ];
        }

        return $status;
    }

    /**
     * The discovered migrations, keyed by name and ordered by filename.
     *
     * @return array<string, string> Migration name => absolute file path.
     */
    public function migrations(): array
    {
        $files = glob(rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);

        $map = [];

        foreach ($files as $file) {
            $map[pathinfo($file, PATHINFO_FILENAME)] = $file;
        }

        return $map;
    }

    /**
     * Instantiate the migration declared by a file.
     */
    private function resolve(string $file): IndexMigration
    {
        if (isset($this->resolved[$file])) {
            return $this->resolved[$file];
        }

        $before = get_declared_classes();
        $returned = require $file;
        $declared = array_diff(get_declared_classes(), $before);

        foreach ($declared as $class) {
            if (is_subclass_of($class, IndexMigration::class)) {
                /** @var class-string<IndexMigration> $class */
                return $this->resolved[$file] = new $class();
            }
        }

        if ($returned instanceof IndexMigration) {
            return $this->resolved[$file] = $returned;
        }

        throw new IndexException(sprintf(
            'Migration file [%s] must declare a class extending %s.',
            $file,
            IndexMigration::class,
        ));
    }
}
