<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Engines;

use CloudMediaSolutions\LaravelScoutOpenSearch\SearchFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $docs = $models->reduce(function ($docs, $model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return $docs;
            }

            $docs[] = [
                "index" => [
                    "_index" => $model->searchableAs(),
                    "_id" => $model->getScoutKey(),
                    ...$model->scoutMetadata()
                ]
            ];

            $docs[] = $searchableData;
        }, []);

        $this->opensearch->bulk(["body" => $docs]);
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

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $models->each(function ($model) {
            $this->opensearch->delete([
                'index' => $model->searchableAs(),
                'id' => $model->getScoutKey()
            ]);
        });
    }
}
