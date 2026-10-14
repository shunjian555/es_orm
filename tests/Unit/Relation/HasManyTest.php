<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Relation;

use EsOrm\Model\ModelCollection;
use EsOrm\Relation\HasMany;
use EsOrm\Tests\Fixtures\Comment;
use EsOrm\Tests\Fixtures\Post;
use EsOrm\Tests\TestCase;
use stdClass;

final class HasManyTest extends TestCase
{
    private function post(array $attributes): Post
    {
        return (new Post())->newInstance($attributes, true);
    }

    private function comment(array $attributes): Comment
    {
        return (new Comment())->newInstance($attributes, true);
    }

    private function relation(Post $parent): HasMany
    {
        return new HasMany($parent, new Comment(), 'article_id', 'id');
    }

    public function test_get_results_returns_every_matching_document(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => [
                ['_id' => '9', '_source' => ['id' => '9', 'article_id' => 1, 'body' => 'A']],
                ['_id' => '10', '_source' => ['id' => '10', 'article_id' => 1, 'body' => 'B']],
            ]],
        ]);
        $relation = $this->relation($this->post(['id' => 1]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertInstanceOf(ModelCollection::class, $result);
        self::assertCount(2, $result);
        self::assertSame(
            ['bool' => ['must' => [['term' => ['article_id' => 1]]]]],
            $this->client->lastParams('search')['body']['query'],
        );
    }

    public function test_get_results_returns_null_when_the_local_key_is_null(): void
    {
        // Arrange
        $relation = $this->relation($this->post([]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertNull($result);
        self::assertSame(0, $this->client->callCount('search'));
    }

    public function test_add_eager_constraints_uses_a_terms_query(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));

        // Act
        $relation->addEagerConstraints([
            $this->post(['id' => 1]),
            $this->post(['id' => 2]),
            $this->post([]),
        ]);

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['terms' => ['article_id' => [1, 2]]]]]],
            $relation->getQuery()->toDsl()['query'],
        );
    }

    public function test_get_eager_results_runs_a_single_search(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '9', '_source' => ['id' => '9', 'article_id' => 1]],
            ]],
        ]);
        $relation = $this->relation($this->post(['id' => 1]));
        $relation->addEagerConstraints([$this->post(['id' => 1]), $this->post(['id' => 2])]);

        // Act
        $result = $relation->getEagerResults();

        // Assert
        self::assertSame(1, $this->client->callCount('search'));
        self::assertCount(1, $result);
    }

    public function test_match_groups_the_related_models_by_foreign_key(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));
        $relation->setRelationName('comments');
        $models = [
            $this->post(['id' => 1]),
            $this->post(['id' => 2]),
            $this->post([]),
        ];
        $results = new ModelCollection([
            $this->comment(['id' => '9', 'article_id' => 1]),
            $this->comment(['id' => '10', 'article_id' => 1]),
            $this->comment(['id' => '11', 'article_id' => 3]),
        ]);

        // Act
        $returned = $relation->match($models, $results);

        // Assert
        self::assertSame($models, $returned);
        self::assertCount(2, $models[0]->getRelation('comments'));
        self::assertSame([], $models[1]->getRelation('comments'));
        self::assertSame([], $models[2]->getRelation('comments'));
    }

    public function test_match_ignores_results_that_are_not_models(): void
    {
        // Arrange
        $relation = $this->relation($this->post(['id' => 1]));
        $relation->setRelationName('comments');
        $models = [$this->post(['id' => 1])];

        // Act
        $relation->match($models, new ModelCollection([new stdClass()]));

        // Assert
        self::assertSame([], $models[0]->getRelation('comments'));
    }
}
