<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Relation;

use EsOrm\Relation\HasOne;
use EsOrm\Relation\Relation;
use EsOrm\Tests\Fixtures\Comment;
use EsOrm\Tests\Fixtures\Company;
use EsOrm\Tests\Fixtures\Post;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class RelationTest extends TestCase
{
    private function relation(): Relation
    {
        return new HasOne(
            (new User())->newInstance(['id' => 1], true),
            new Comment(),
            'user_id',
            'id',
        );
    }

    public function test_it_exposes_the_parent_and_related_models(): void
    {
        // Act
        $relation = $this->relation();

        // Assert
        self::assertSame('users', $relation->getParent()->getIndex());
        self::assertSame('comments', $relation->getRelated()->getIndex());
    }

    public function test_it_exposes_the_key_names(): void
    {
        // Act
        $relation = $this->relation();

        // Assert
        self::assertSame('user_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }

    public function test_relation_name_is_null_until_set(): void
    {
        // Act
        $relation = $this->relation();

        // Assert
        self::assertNull($relation->getRelationName());
        self::assertSame($relation, $relation->setRelationName('comment'));
        self::assertSame('comment', $relation->getRelationName());
    }

    public function test_get_query_builds_a_query_for_the_related_model(): void
    {
        // Act
        $query = $this->relation()->getQuery();

        // Assert
        self::assertSame('comments', $query->getIndex());
    }

    public function test_get_query_is_memoised(): void
    {
        // Arrange
        $relation = $this->relation();

        // Act / Assert
        self::assertSame($relation->getQuery(), $relation->getQuery());
    }

    public function test_nested_eager_loading_issues_one_query_per_relation_level(): void
    {
        // Arrange
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_source' => ['id' => '1', 'company_id' => 5, 'title' => 'Hi']],
            ]],
        ]);
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '5', '_source' => ['id' => '5', 'name' => 'Acme']],
            ]],
        ]);
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_source' => ['id' => '1', 'company_id' => 5, 'title' => 'Hi']],
            ]],
        ]);

        // Act
        $posts = Post::with('company.posts')->get();

        // Assert
        self::assertSame(3, $this->client->callCount('search'));

        $company = $posts->first()->getRelation('company');
        self::assertInstanceOf(Company::class, $company);
        self::assertCount(1, $company->getRelation('posts'));
    }

    public function test_eager_loading_belongs_to_issues_a_single_batch_query(): void
    {
        // Arrange
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => [
                ['_id' => '1', '_source' => ['id' => '1', 'author_id' => 5]],
                ['_id' => '2', '_source' => ['id' => '2', 'author_id' => 6]],
            ]],
        ]);
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => [
                ['_id' => '5', '_source' => ['id' => '5', 'name' => 'Ada']],
                ['_id' => '6', '_source' => ['id' => '6', 'name' => 'Alan']],
            ]],
        ]);

        // Act
        $posts = Post::with('author')->get();

        // Assert
        self::assertSame(2, $this->client->callCount('search'));
        self::assertSame(
            ['bool' => ['must' => [['terms' => ['id' => [5, 6]]]]]],
            $this->client->lastParams('search')['body']['query'],
        );
        self::assertSame('Ada', $posts->first()->getRelation('author')->getAttribute('name'));
    }

    public function test_eager_loading_has_many_issues_a_single_batch_query(): void
    {
        // Arrange
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => [
                ['_id' => '1', '_source' => ['id' => '1']],
                ['_id' => '2', '_source' => ['id' => '2']],
            ]],
        ]);
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '9', '_source' => ['id' => '9', 'article_id' => 1, 'body' => 'Nice']],
            ]],
        ]);

        // Act
        $posts = Post::with('comments')->get();

        // Assert
        self::assertSame(2, $this->client->callCount('search'));
        self::assertSame(
            ['bool' => ['must' => [['terms' => ['article_id' => ['1', '2']]]]]],
            $this->client->lastParams('search')['body']['query'],
        );
        self::assertCount(1, $posts->first()->getRelation('comments'));
    }
}
