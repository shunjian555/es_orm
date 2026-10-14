<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Query\Search;
use EsOrm\Tests\Fixtures\Author;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use stdClass;

/**
 * Unit tests for the multi search entry point.
 */
final class SearchTest extends TestCase
{
    public function test_multi_sends_a_single_msearch_request_for_every_builder(): void
    {
        // Arrange
        $this->client->setResponse('msearch', [
            'responses' => [
                ['took' => 1, 'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]]],
                ['took' => 1, 'hits' => ['total' => ['value' => 0], 'hits' => []]],
            ],
        ]);

        // Act
        $results = Search::multi([
            'users' => User::query()->match('name', 'Tom'),
            'authors' => Author::query()->term('name', 'Jane'),
        ]);

        // Assert
        self::assertSame(['users', 'authors'], array_keys($results));
        self::assertSame(1, $results['users']->total());
        self::assertSame('Tom', $results['users']->hits()->first()->name);
        self::assertSame(0, $results['authors']->total());
    }

    public function test_multi_builds_the_expected_msearch_body(): void
    {
        // Arrange
        $this->client->setResponse('msearch', ['responses' => [[], []]]);

        // Act
        Search::multi([
            'users' => User::query()->match('name', 'Tom')->limit(5),
            'authors' => Author::query()->term('name', 'Jane'),
        ]);

        // Assert
        $body = $this->client->lastParams('msearch')['body'];
        self::assertSame('users', $body[0]['index']);
        self::assertEquals(['bool' => ['must' => [['match' => ['name' => ['query' => 'Tom']]]]]], $body[1]['query']);
        self::assertSame(5, $body[1]['size']);
        self::assertSame('authors', $body[2]['index']);
        self::assertEquals(['bool' => ['must' => [['term' => ['name' => 'Jane']]]]], $body[3]['query']);
    }

    public function test_multi_returns_an_empty_array_without_touching_the_client(): void
    {
        // Act
        $results = Search::multi([]);

        // Assert
        self::assertSame([], $results);
        self::assertSame(0, $this->client->callCount('msearch'));
    }

    public function test_multi_rejects_non_builders(): void
    {
        // Assert
        $this->expectException(InvalidQueryException::class);

        // Act
        Search::multi(['bad' => new stdClass()]);
    }
}
