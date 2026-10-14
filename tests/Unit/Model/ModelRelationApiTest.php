<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Relation\HasOne;
use EsOrm\Tests\Fixtures\Article;
use EsOrm\Tests\Fixtures\Author;
use EsOrm\Tests\Fixtures\Comment;
use EsOrm\Tests\TestCase;
use ReflectionClass;

/**
 * Covers the relation introspection API of {@see \EsOrm\Model\Model}:
 * hasOne(), resolveRelation(), relationLoaded(), load(), loadMissing() and
 * relationLoadedAndNotEmpty().
 */
final class ModelRelationApiTest extends TestCase
{
    public function test_it_builds_a_has_one_relation_with_the_default_foreign_key(): void
    {
        // Act
        $relation = (new Author())->hasOne(Comment::class);

        // Assert: the default foreign key is the snake case of the parent.
        $foreignKey = (new ReflectionClass(HasOne::class))->getProperty('foreignKey');
        $foreignKey->setAccessible(true);

        self::assertInstanceOf(HasOne::class, $relation);
        self::assertSame('author_id', $foreignKey->getValue($relation));
    }

    public function test_it_resolves_a_declared_relation_by_name(): void
    {
        // Act
        $relation = (new Article())->resolveRelation('author');

        // Assert
        self::assertInstanceOf(\EsOrm\Relation\BelongsTo::class, $relation);
    }

    public function test_it_rejects_an_unknown_relation_name(): void
    {
        // Expect
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('does not exist');

        // Act
        (new Article())->resolveRelation('ghost');
    }

    public function test_it_rejects_a_method_that_does_not_return_a_relation(): void
    {
        // Expect
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('must return an instance of');

        // Act
        (new Article())->resolveRelation('getIndexName');
    }

    private function article(): Article
    {
        /** @var Article $article */
        $article = (new Article())->setRawAttributes(['id' => '10', 'author_id' => '1', 'title' => 'ES']);

        return $article;
    }

    public function test_it_loads_a_belongs_to_relation_on_an_existing_model(): void
    {
        // Arrange
        $article = $this->article();
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_index' => 'authors', '_source' => ['id' => '1', 'name' => 'Alice']],
            ]],
        ]);

        // Act
        $article->load('author');

        // Assert
        self::assertTrue($article->relationLoaded('author'));
        self::assertInstanceOf(Author::class, $article->author);
        self::assertSame('Alice', $article->author->getAttribute('name'));
    }

    public function test_load_missing_does_not_requery_a_loaded_relation(): void
    {
        // Arrange
        $article = $this->article();
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_index' => 'authors', '_source' => ['id' => '1', 'name' => 'Alice']],
            ]],
        ]);
        $article->load('author');

        // Act
        $article->loadMissing('author');

        // Assert: no second query was issued.
        self::assertSame(1, $this->client->callCount('search'));
    }

    public function test_load_missing_queries_an_unloaded_relation(): void
    {
        // Arrange
        $article = $this->article();
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_index' => 'authors', '_source' => ['id' => '1', 'name' => 'Alice']],
            ]],
        ]);

        // Act
        $article->loadMissing(['author']);

        // Assert
        self::assertTrue($article->relationLoaded('author'));
        self::assertSame(1, $this->client->callCount('search'));
    }

    public function test_relation_loaded_and_not_empty_reflects_the_match_result(): void
    {
        // Arrange: the author does not exist, so the loaded relation is null.
        $article = $this->article();
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);
        $article->load('author');

        // Assert
        self::assertTrue($article->relationLoaded('author'));
        self::assertFalse($article->relationLoadedAndNotEmpty('author'));
        self::assertFalse($article->relationLoadedAndNotEmpty('comments'));
    }

    public function test_relation_loaded_and_not_empty_is_true_for_a_matched_relation(): void
    {
        // Arrange
        $article = $this->article();
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [
                ['_id' => '1', '_index' => 'authors', '_source' => ['id' => '1', 'name' => 'Alice']],
            ]],
        ]);

        // Act
        $article->load('author');

        // Assert
        self::assertTrue($article->relationLoadedAndNotEmpty('author'));
    }
}
