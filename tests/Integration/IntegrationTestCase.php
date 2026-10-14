<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration;

use EsOrm\Client\ConnectionManager;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Model\Model;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Throwable;

/**
 * Base class for tests that talk to a real Elasticsearch node.
 *
 * The suite is skipped unless `ES_INTEGRATION=true`, so the default
 * `vendor/bin/phpunit` run never fails when no node is available.
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    protected ConnectionManager $manager;

    protected ConnectionInterface $connection;

    /**
     * Prefix used for the throw-away indices created by the tests.
     */
    protected string $prefix = 'es_orm_it_';

    /**
     * @var list<string>
     */
    private array $createdIndices = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->integrationEnabled()) {
            self::markTestSkipped('Set ES_INTEGRATION=true (and provide ES_HOST/ES_USERNAME/ES_PASSWORD) to run the integration suite.');
        }

        Model::setConnectionManager(null);
        Model::setEventDispatcher(null);

        $this->manager = new ConnectionManager([
            'default' => 'default',
            'connections' => [
                'default' => [
                    'hosts' => [$this->env('ES_HOST', 'http://localhost:9200')],
                    'username' => $this->env('ES_USERNAME', 'elastic'),
                    'password' => $this->env('ES_PASSWORD', ''),
                ],
            ],
        ]);

        Model::setConnectionManager($this->manager);
        $this->connection = $this->manager->connection();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIndices as $index) {
            try {
                $this->connection->request('deleteIndex', ['index' => $index, 'ignore_unavailable' => true]);
            } catch (Throwable) {
                // Best effort clean-up: a missing index must not fail the test.
            }
        }

        $this->createdIndices = [];

        Model::setConnectionManager(null);

        parent::tearDown();
    }

    protected function integrationEnabled(): bool
    {
        return filter_var($this->env('ES_INTEGRATION', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * A unique, throw-away index name; it is deleted in tearDown().
     */
    protected function uniqueIndex(string $hint = 'test'): string
    {
        $index = $this->prefix . $hint . '_' . bin2hex(random_bytes(4));
        $this->createdIndices[] = $index;

        return $index;
    }

    protected function registerIndex(string $index): string
    {
        if (!in_array($index, $this->createdIndices, true)) {
            $this->createdIndices[] = $index;
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function es(string $operation, array $params): array
    {
        return $this->connection->request($operation, $params);
    }
}
