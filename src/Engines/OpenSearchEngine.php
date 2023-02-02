<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Engines;

use CloudMediaSolutions\LaravelScoutOpenSearch\Paginator\ScrollPaginator;
use CloudMediaSolutions\LaravelScoutOpenSearch\Paginator\ScrollPaginatorRaw;
use CloudMediaSolutions\LaravelScoutOpenSearch\SearchFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use OpenSearch\Client;

class OpenSearchEngine extends Engine
{
    public function __construct(public Client $opensearch)
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $payload = $models->reduce(function ($payload, $model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return $payload;
            }

            $payload[] = [
                'index' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey(),
                    ...$model->scoutMetadata()
                ]
            ];

            $payload[] = $searchableData;

            return $payload;
        }, []);

        $this->opensearch->bulk(['body' => $payload]);
    }

    /**
     * @param  array{items: mixed[]|null}|null  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results === null) {
            return $model->newCollection();
        }

        if (!isset($results['hits'])) {
            return $model->newCollection();
        }

        if ($results['hits'] === []) {
            return $model->newCollection();
        }

        $objectIds = $this->mapIds($results)->toArray();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds, false);
            })->sortBy(function ($model) use ($objectIdPositions): int {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * @param  array{items: mixed[]|null}|null  $results
     */
    public function mapIds($results): Collection
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['hits']['hits'])->pluck('_id');
    }

    /**
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $searchBody = SearchFactory::create($builder, $options);
        if ($builder->callback) {
            /** @var callable */
            $callback = $builder->callback;

            return call_user_func(
                $callback,
                $this->opensearch,
                $searchBody
            );
        }

        $model = $builder->model;
        $indexName = $builder->index ?: $model->searchableAs();
        return $this->opensearch->search(['index' => $indexName, 'body' => $searchBody->toArray()]);
    }

    /**
     * @param  mixed  $perPage
     * @param  mixed  $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, array_filter([
            'from' => $perPage * ($page - 1),
            'size' => $perPage,
        ]));
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  array{items: mixed[]|null}|null  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *
     * @return \Illuminate\Support\LazyCollection|mixed
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results === null) {
            return LazyCollection::make($model->newCollection());
        }

        if (!isset($results['hits'])) {
            return LazyCollection::make($model->newCollection());
        }

        if ($results['hits'] === []) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = $this->mapIds($results)->toArray();

        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds, false);
            })->sortBy(function ($model) use ($objectIdPositions): int {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * @param  array<string, mixed>|null  $results
     *
     * @return int|mixed
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'] ?? 0;
    }

    public function flush($model): void
    {
        $this->opensearch->deleteByQuery([
            'index' => $model->searchableAs(),
            'body'  => [
                'query' => (new MatchAllQuery())->toArray()
            ]
        ]);
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array<string, mixed>  $options
     */
    public function createIndex($name, array $options = []): array
    {
        $body = array_replace_recursive(
            config('opensearch.indices.default') ?? [],
            config('opensearch.indices.'.$name) ?? []);

        return $this->opensearch->indices()->create(['index' => $name, 'body' => $body]);
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     */
    public function deleteIndex($name): array
    {
        return $this->opensearch->indices()->delete(['index' => $name]);
    }

    /**
     * @inheritDoc
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $payload = $models->reduce(function ($payload, $model) {
            $payload[] = [
                'delete' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey()
                ]
            ];

            return $payload;
        }, []);

        $this->opensearch->bulk(['body' => $payload]);
    }

    /**
     * @see https://opensearch.org/docs/latest/opensearch/search/paginate/#the-search_after-parameter
     *
     * @param Builder $builder
     * @param integer $perPage
     * @param string $cursorName
     * @param Cursor|string|null $cursor
     * @return CursorPaginator
     */
    public function cursorPaginate(
        Builder $builder, 
        int $perPage, 
        string $cursorName = 'cursor', 
        $cursor = null
    ): CursorPaginator {
        if (empty($builder->orders)) {
            $builder->orderBy("_id");
        }

        $cursor = $this->resolveCursor($cursor, $cursorName);
        $cols = array_column($builder->orders, 'column');

        $response = $this->performCursorSearch($builder, $perPage, $cols, $cursor);

        $items = $builder->model->newCollection(
            $this->map($builder, $response, $builder->model)->all()
        );

        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $cols
        ];

        return new ScrollPaginator($items, $perPage, $response, $cursor, $options);
    }

    private function resolveCursor($cursor, $cursorName): ?Cursor
    {
        if ($cursor instanceof Cursor) {
            return $cursor;
        }

        return is_string($cursor) 
            ? Cursor::fromEncoded($cursor)
            : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
    }

    private function performCursorSearch(
        Builder $builder, 
        int $perPage, 
        array $cols, 
        $cursor
    ) {
        $searchAfter = $cursor !== null 
            ? $cursor->parameters($cols)
            : null;

        if ($cursor !== null && 
            $cursor->pointsToPreviousItems()
        ) {
            $builder->orders = array_map(
                function ($order) {
                    $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';
                    return $order;
                }, 
                $builder->orders
            );
        }

        return $this->performSearch($builder, array_filter([
            'size' => $perPage + 1,
            'searchAfter' => $searchAfter
        ]));
    }
}
