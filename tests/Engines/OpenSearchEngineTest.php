<?php

namespace Tests\Engines;

use CloudMediaSolutions\LaravelScoutOpenSearch\Engines\OpenSearchEngine;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Mockery;
use OpenSearch\Client;
use OpenSearch\Endpoints\Bulk;
use OpenSearch\Endpoints\Delete;
use OpenSearch\Endpoints\Search;
use Orchestra\Testbench\TestCase;
use stdClass;
use Tests\Fixtures\TestModel;

class OpenSearchEngineTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(Client::class);
        $this->engine = new OpenSearchEngine($this->client);
    }

    public function test_update_adds_document()
    {
        $this->client->shouldReceive('bulk')->once()->with([
            'body' => [
                [
                    'index' => [
                        '_index' => 'table',
                        '_id' => 100,
                    ]
                ],
                [
                    'id' => 100
                ],
            ],
        ])->andReturns(Bulk::class);

        $this->engine->update(Collection::make([new TestModel(['id' => 100])]));
    }

    public function test_map_correctly_maps_results_to_models()
    {
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive(['getKeyName' => 'id']);
        $model->shouldReceive('getScoutModelsByIds')->andReturn($models = Collection::make([new TestModel(['id' => 1])]));
        $builder = Mockery::mock(Builder::class);

        $results = $this->engine->map($builder, [
            'hits' => [
                'hits' => [
                    ['_id' => 1],
                ]
            ],
        ], $model);

        $this->assertEquals(1, count($results));
    }

    public function test_map_method_respects_order()
    {
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive(['getKeyName' => 'id']);
        $model->shouldReceive('getScoutModelsByIds')->andReturn(Collection::make([
            new TestModel(['id' => 1]),
            new TestModel(['id' => 2]),
            new TestModel(['id' => 3]),
            new TestModel(['id' => 4]),
        ]));

        $builder = Mockery::mock(Builder::class);

        $results = $this->engine->map($builder, [
            'hits' => [
                'hits' => [
                    ['_id' => 1],
                    ['_id' => 2],
                    ['_id' => 4],
                    ['_id' => 3],
                ]
            ],
        ], $model);

        $this->assertEquals(4, count($results));
        $this->assertEquals([
            0 => ['id' => 1],
            1 => ['id' => 2],
            2 => ['id' => 4],
            3 => ['id' => 3],
        ], $results->toArray());
    }

    public function test_search()
    {
        $perPage = 5;
        $page = 2;

        $this->client->shouldReceive('search')->once()->with([
            'index' => 'table',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => 'mustang'
                    ]
                ],
                'size' => $perPage,
                'from' => ($page - 1) * $perPage,
            ]
        ]);

        $builder = new Builder(new TestModel(), 'mustang');
        $this->engine->paginate($builder, $perPage, $page);
    }

    public function test_search_with_filter()
    {
        $perPage = 5;
        $page = 2;

        $this->client->shouldReceive('search')->once()->with([
            'index' => 'table',
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            [
                                'term' => [
                                    'id' => 123,
                                ],
                            ],
                            [
                                'terms' => [
                                    'status' => [
                                        'active',
                                        'inactive',
                                    ],
                                ],
                            ],
                        ],
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => 'mustang'
                                ],
                            ],
                        ],
                    ],

                ],
                'size' => $perPage,
                'from' => ($page - 1) * $perPage,
            ]
        ])->andReturns(Search::class);

        $builder = new Builder(new TestModel(), 'mustang');
        $builder->where('id', '123');
        $builder->whereIn('status', ['active', 'inactive']);
        $this->engine->paginate($builder, $perPage, $page);
    }

    public function test_delete_document()
    {
        $this->client->shouldReceive('bulk')->once()->with([
            'body' => [
                [
                    "delete" => [
                        "_index" => "table",
                        "_id" => 100
                    ]
                ]
            ]
        ])->andReturns(Bulk::class);

        $this->engine->delete(Collection::make([new TestModel(['id' => 100])]));
    }

}
