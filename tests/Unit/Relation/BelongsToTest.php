<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Relation;

use EsOrm\Model\ModelCollection;
use EsOrm\Relation\BelongsTo;
use EsOrm\Tests\Fixtures\Author;
use EsOrm\Tests\Fixtures\Post;
use EsOrm\Tests\TestCase;
use stdClass;

final class BelongsToTest extends TestCase
{
    private function post(array $attributes): Post
    {
        return (new Post())->newInstance($attributes, true);
    }

    private function author(array $attributes): Author
    {
        return (new Author())->newInstance($attributes, true);
    }

    private function relation(Post $parent): BelongsTo
    {
        return new BelongsTo($parent, new Author(), 'author_id', 'id');
    }

    public function test_get_results_matches_the_owner_by_local_key(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '5', '_source' => ['id' => '5', 'name' => 'Ada']],
            ]],
        ]);
        $relation = $this->relation($this->post(['id' => 1, 'author_id' => 5]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertInstanceOf(Author::class, $result);
        self::assertSame('Ada', $result->getAttribute('name'));
        self::assertSame(
            ['bool' => ['must' => [['term' => ['id' => 5]]]]],
            $this->client->lastParams('search')['body']['query'],
        );
    }

    public function test_get_results_returns_null_when_the_foreign_key_is_null(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertNull($result);
        self::assertSame(0, $this->client->callCount('search'));
    }

    public function test_add_eager_constraints_uses_a_terms_query_on_the_owner_key(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1, 'author_id' => 5]));

        // Act
        $relation->addEagerConstraints([
            $this->post(['id' => 1, 'author_id' => 5]),
            $this->post(['id' => 2, 'author_id' => 6]),
            $this->post(['id' => 3]),
        ]);

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['terms' => ['id' => [5, 6]]]]]],
            $relation->getQuery()->toDsl()['query'],
        );
    }

    public function test_get_eager_results_runs_a_single_search(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '5', '_source' => ['id' => '5', 'name' => 'Ada']],
            ]],
        ]);
        $relation = $this->relation($this->post(['id' => 1, 'author_id' => 5]));
        $relation->addEagerConstraints([$this->post(['id' => 1, 'author_id' => 5])]);

        // Act
        $result = $relation->getEagerResults();

        // Assert
        self::assertSame(1, $this->client->callCount('search'));
        self::assertCount(1, $result);
    }

    public function test_match_assigns_the_related_model_by_owner_key(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));
        $relation->setRelationName('author');
        $models = [
            $this->post(['id' => 1, 'author_id' => 5]),
            $this->post(['id' => 2, 'author_id' => 7]),
            $this->post(['id' => 3]),
        ];
        $results = new ModelCollection([
            $this->author(['id' => '5', 'name' => 'Ada']),
            $this->author(['id' => '9', 'name' => 'Alan']),
        ]);

        // Act
        $returned = $relation->match($models, $results);

        // Assert
        self::assertSame($models, $returned);
        self::assertSame('Ada', $models[0]->getRelation('author')->getAttribute('name'));
        self::assertNull($models[1]->getRelation('author'));
        self::assertNull($models[2]->getRelation('author'));
    }

    public function test_match_ignores_results_that_are_not_models(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));
        $relation->setRelationName('author');
        $models = [$this->post(['id' => 1, 'author_id' => 5])];

        // Act
        $relation->match($models, new ModelCollection([new stdClass()]));

        // Assert
        self::assertNull($models[0]->getRelation('author'));
    }
}
