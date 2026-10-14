<?php

declare(strict_types=1);

namespace EsOrm\Query;

use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Model\ModelCollection;
use EsOrm\Model\SearchResult;

/**
 * High level entry point for Elasticsearch multi search (`_msearch`).
 *
 * It sends several independent query builders in a single HTTP request and
 * returns one {@see SearchResult} per input query, preserving the input keys.
 * Every builder is expected to run on the same connection (the connection of
 * the first builder is used).
 *
 * Usage:
 *
 * ```php
 * $results = Search::multi([
 *     'users'    => User::query()->match('name', 'Tom'),
 *     'articles' => Article::query()->term('status', 'published'),
 * ]);
 *
 * $results['users']->hits();      // ModelCollection
 * $results['users']->total();     // total hits
 * ```
 */
final class Search
{
    /**
     * Run many searches in one `_msearch` request.
     *
     * @param array<array-key, Builder> $searches
     * @return array<array-key, SearchResult>
     *
     * @throws InvalidQueryException When a value is not a {@see Builder}.
     */
    public static function multi(array $searches): array
    {
        $builders = [];

        foreach ($searches as $key => $search) {
            if (!$search instanceof Builder) {
                throw new InvalidQueryException(
                    'Search::multi() expects an array of EsOrm\\Query\\Builder instances.',
                );
            }

            $builders[$key] = $search;
        }

        if ($builders === []) {
            return [];
        }

        $firstKey = array_key_first($builders);
        $first = $builders[$firstKey];
        $connection = $first->connectionInstance();

        $body = [];

        foreach ($builders as $builder) {
            $body[] = ['index' => $builder->getIndex()];
            $body[] = $builder->toDsl();
        }

        $response = $connection->request('msearch', ['body' => $body]);
        $responses = $response['responses'] ?? [];
        $responses = is_array($responses) ? array_values($responses) : [];

        $results = [];
        $position = 0;

        foreach ($builders as $key => $builder) {
            $raw = $responses[$position] ?? [];
            $raw = is_array($raw) ? $raw : [];

            $results[$key] = SearchResult::fromResponse($raw, self::hydrate($builder, $raw));

            ++$position;
        }

        return $results;
    }

    /**
     * Hydrate the hits of a single multi search response.
     *
     * @param array<string, mixed> $response
     */
    private static function hydrate(Builder $builder, array $response): ModelCollection
    {
        $hitsNode = $response['hits'] ?? [];
        $hitsNode = is_array($hitsNode) ? $hitsNode : [];
        $documents = $hitsNode['hits'] ?? [];
        $documents = is_array($documents) ? $documents : [];

        $models = [];

        foreach ($documents as $hit) {
            if (is_array($hit)) {
                $models[] = $builder->getModel()->newFromHit($hit);
            }
        }

        return new ModelCollection($models);
    }
}
