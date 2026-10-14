<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Relation;

use EsOrm\Model\ModelCollection;
use EsOrm\Relation\HasOne;
use EsOrm\Tests\Fixtures\Comment;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use stdClass;

final class HasOneTest extends TestCase
{
    private function user(array $attributes): User
    {
        return (new User())->newInstance($attributes, true);
    }

    private function comment(array $attributes): Comment
    {
        return (new Comment())->newInstance($attributes, true);
    }

    private function relation(User $parent): HasOne
    {
        return new HasOne($parent, new Comment(), 'user_id', 'id');
    }

    public function test_get_results_matches_the_related_document_by_foreign_key(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '10', '_source' => ['id' => '10', 'user_id' => 1, 'body' => 'Hi']],
            ]],
        ]);
        $relation = $this->relation($this->user(['id' => 1]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertInstanceOf(Comment::class, $result);
        self::assertSame('10', $result->getAttribute('id'));
        self::assertSame(
            ['bool' => ['must' => [['term' => ['user_id' => 1]]]]],
            $this->client->lastParams('search')['body']['query'],
        );
    }

    public function test_get_results_returns_null_when_the_local_key_is_null(): void
    {
        // Arrange
        $relation = $this->relation($this->user([]));

        // Act
        $result = $relation->getResults();

        // Assert
        self::assertNull($result);
        self::assertSame(0, $this->client->callCount('search'));
    }

    public function test_add_eager_constraints_deduplicates_and_skips_null_keys(): void
    {
        // Arrange
        $relation = $this->relation($this->user(['id' => 1]));
        $models = [
            $this->user(['id' => 1]),
            $this->user(['id' => 2]),
            $this->user(['id' => 1]),
            $this->user([]),
        ];

        // Act
        $relation->addEagerConstraints($models);

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['terms' => ['user_id' => [1, 2]]]]]],
            $relation->getQuery()->toDsl()['query'],
        );
    }

    public function test_get_eager_results_runs_a_single_search(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '10', '_source' => ['id' => '10', 'user_id' => 1]],
            ]],
        ]);
        $relation = $this->relation($this->user(['id' => 1]));
        $relation->addEagerConstraints([$this->user(['id' => 1]), $this->user(['id' => 2])]);

        // Act
        $result = $relation->getEagerResults();

        // Assert
        self::assertSame(1, $this->client->callCount('search'));
        self::assertCount(1, $result);
    }

    public function test_match_assigns_the_related_model_by_local_key(): void
    {
        // Arrange
        $relation = $this->relation($this->user(['id' => 1]));
        $relation->setRelationName('comment');
        $models = [
            $this->user(['id' => 1]),
            $this->user(['id' => 2]),
            $this->user([]),
        ];
        $results = new ModelCollection([
            $this->comment(['id' => '10', 'user_id' => 1]),
            $this->comment(['id' => '11', 'user_id' => 99]),
        ]);

        // Act
        $returned = $relation->match($models, $results);

        // Assert
        self::assertSame($models, $returned);
        self::assertSame('10', $models[0]->getRelation('comment')->getAttribute('id'));
        self::assertNull($models[1]->getRelation('comment'));
        self::assertNull($models[2]->getRelation('comment'));
    }

    public function test_match_ignores_results_that_are_not_models(): void
    {
        // Arrange
        $relation = $this->relation($this->user(['id' => 1]));
        $relation->setRelationName('comment');
        $models = [$this->user(['id' => 1])];
        $results = new ModelCollection([new stdClass()]);

        // Act
        $relation->match($models, $results);

        // Assert
        self::assertNull($models[0]->getRelation('comment'));
    }
}
