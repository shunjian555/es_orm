<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit;

use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class SmokeTest extends TestCase
{
    public function test_it_compiles_a_term_query_into_the_request_body(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'took' => 1,
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        $dsl = User::query()->where('status', 'active')->toDsl();

        // Assert
        self::assertSame(['bool' => ['must' => [['term' => ['status' => 'active']]]]], $dsl['query']);
    }

    public function test_it_sends_the_compiled_body_to_the_client(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        User::query()->where('status', 'active')->get();

        // Assert
        $params = $this->client->lastParams('search');
        self::assertSame('users', $params['index']);
        self::assertSame(['term' => ['status' => 'active']], $params['body']['query']['bool']['must'][0]);
    }
}
