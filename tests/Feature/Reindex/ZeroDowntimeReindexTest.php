<?php

declare(strict_types=1);

namespace EsOrm\Tests\Feature\Reindex;

use EsOrm\Index\AliasManager;
use EsOrm\Index\ReindexManager;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;
use stdClass;

/**
 * A zero-downtime index upgrade: build v2, reindex v1 into it, then atomically
 * switch the alias — the alias never stops resolving.
 */
final class ZeroDowntimeReindexTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    public function test_a_zero_downtime_reindex_and_atomic_alias_switch(): void
    {
        // Arrange: the live (v1) index, seeded and exposed through an alias.
        $v1 = $this->createUserIndex('zd_v1');
        $this->seedUsers($v1, [
            ['id' => '1', 'name' => 'Alice', 'status' => 'active'],
            ['id' => '2', 'name' => 'Bob', 'status' => 'active'],
            ['id' => '3', 'name' => 'Carol', 'status' => 'active'],
        ]);

        $alias = $this->prefix . 'zd_alias_' . bin2hex(random_bytes(4));
        $aliases = AliasManager::on($this->manager);
        $aliases->createAlias($v1, $alias);

        // The alias resolves before the upgrade.
        self::assertSame([$v1], array_keys($this->es('getAlias', ['name' => $alias])));
        self::assertSame(3, $this->countThrough($alias));

        // Act: build v2 and copy the documents over.
        $v2 = $this->createUserIndex('zd_v2');

        $reindex = ReindexManager::on($this->manager);
        $result = $reindex->reindexTo($v1, $v2, ['refresh' => true]);

        // Assert: v2 holds the same number of documents as v1.
        self::assertSame(3, (int) $result['created']);
        self::assertSame(3, $this->countThrough($v2));

        // Act: atomically move the alias from v1 to v2.
        $switched = $aliases->switchAlias($alias, $v2);

        // Assert: the alias now points at exactly v2 (no longer at v1) and keeps
        // serving the same number of documents — readers never saw a gap.
        self::assertTrue($switched);
        self::assertSame([$v2], array_keys($this->es('getAlias', ['name' => $alias])));
        self::assertSame(3, $this->countThrough($alias));

        $hits = $this->rawMatchAll($alias)['hits']['hits'];
        foreach ($hits as $hit) {
            self::assertSame($v2, $hit['_index']);
        }
    }

    private function countThrough(string $index): int
    {
        $response = $this->es('count', [
            'index' => $index,
            'body' => ['query' => ['match_all' => new stdClass()]],
        ]);

        return (int) $response['count'];
    }
}
