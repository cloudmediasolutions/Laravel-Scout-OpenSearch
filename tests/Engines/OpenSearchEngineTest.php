<?php

namespace Tests\Engines;

use CloudMediaSolutions\LaravelScoutOpenSearch\Engines\OpenSearchEngine;
use CloudMediaSolutions\LaravelScoutOpenSearch\Providers\OpenSearchServiceProvider;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use OpenSearch
\Sort\FieldSort;
use OpenSearch\Client;
use OpenSearch\Endpoints\Bulk;
use OpenSearch\Endpoints\Search;
use Orchestra\Testbench\TestCase;
use stdClass;
use Tests\Fixtures\TestModel;

class OpenSearchEngineTest extends TestCase
{
    private MockInterface|LegacyMockInterface|null $client = null;
    private ?OpenSearchEngine $engine = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(Client::class);
        $this->engine = new OpenSearchEngine($this->client);
    }


    protected function getPackageProviders($app)
    {
        return [
            OpenSearchServiceProvider::class,
        ];
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
        /**
         * @var MockInterface|LegacyMockInterface $model
         */
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
        /**
         * @var MockInterface|LegacyMockInterface $model
         */
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

    public function test_cursor_paginate()
    {
        $perPage = 3;
        $modelMock = Mockery::mock(new TestModel());
        $modelMock->shouldReceive('getScoutModelsByIds')
            ->andReturn(
            collect([
                new TestModel(['id' => 1]),
                new TestModel(['id' => 2]),
                new TestModel(['id' => 3]),
                new TestModel(['id' => 4])
            ])
        );

        $this->client->shouldReceive('search')->with([
            'index' => 'table',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => 'mustang'
                    ]
                ],
                'sort' => [
                    [
                        'id' => [
                            'order' => 'asc'
                        ]
                    ]
                ],
                'size' => $perPage + 1,
            ]
        ])->andReturns([
            'hits' => [
                'total' => 5,
                'hits' => [
                    [
                        '_id' => 1,
                        '_source' => [
                            'id' => 1
                        ],
                        'sort' => [
                            1
                        ]
                    ],
                    [
                        '_id' => 2,
                        '_source' => [
                            'id' => 2
                        ],
                        'sort' => [
                            2
                        ]
                    ],
                    [
                        '_id' => 3,
                        '_source' => [
                            'id' => 3
                        ],
                        'sort' => [
                            3
                        ]
                    ],
                    [
                        '_id' => 4,
                        '_source' => [
                            'id' => 4
                        ],
                        'sort' => [
                            4
                        ]
                    ]
                ]
            ],
        ]);

        $builder = new Builder($modelMock, 'mustang');
        $builder->orderBy('id');
        /**
         * @var CursorPaginator $paginator
         */
        $paginator = $this->engine->cursorPaginate($builder, $perPage);

        $this->assertEquals(3, $paginator->nextCursor()->parameter('id'));
        $this->assertNull($paginator->previousCursor());

        return $paginator->nextCursor();
    }

    /**
     * @depends test_cursor_paginate
     *
     */
    public function test_cursor_paginate_next(Cursor $cursor)
    {
        $perPage = 3;
        $modelMock = Mockery::mock(new TestModel());
        $modelMock->shouldReceive('getScoutModelsByIds')
            ->andReturn(
            collect([
                new TestModel(['id' => 4]),
                new TestModel(['id' => 5])
            ])
        );

        $this->client->shouldReceive('search')->with([
            'index' => 'table',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => 'mustang'
                    ]
                ],
                'sort' => [
                    [
                        'id' => [
                            'order' => 'asc'
                        ]
                    ]
                ],
                'size' => $perPage + 1,
                'search_after' => [
                    $cursor->parameter('id')
                ]
            ]
        ])->andReturns([
            'hits' => [
                'total' => 5,
                'hits' => [
                    [
                        '_id' => 4,
                        '_source' => [
                            'id' => 1
                        ],
                        'sort' => [
                            4
                        ]
                    ],
                    [
                        '_id' => 5,
                        '_source' => [
                            'id' => 2
                        ],
                        'sort' => [
                            5
                        ]
                    ],
                ]
            ],
        ]);

        $builder = new Builder($modelMock, 'mustang');
        $builder->orderBy('id');
        /**
         * @var CursorPaginator $paginator
         */
        $paginator = $this->engine->cursorPaginate($builder, $perPage, 'cursor', $cursor);

        $this->assertEquals(4, $paginator->previousCursor()->parameter('id'));
        $this->assertNull($paginator->nextCursor());

        return $paginator->previousCursor();
    }   

    /**
     * @depends test_cursor_paginate_next
     */
    public function test_cursor_paginate_previous(Cursor $cursor)
    {
        $perPage = 3;
        $modelMock = Mockery::mock(new TestModel());
        $modelMock->shouldReceive('getScoutModelsByIds')
            ->andReturn(
            collect([
                new TestModel(['id' => 1]),
                new TestModel(['id' => 2]),
                new TestModel(['id' => 3])
            ])
        );

        $this->client->shouldReceive('search')->with([
            'index' => 'table',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => 'mustang'
                    ]
                ],
                'sort' => [
                    [
                        'id' => [
                            'order' => 'desc'
                        ]
                    ]
                ],
                'size' => $perPage + 1,
                'search_after' => [
                    $cursor->parameter('id')
                ]
            ]
        ])->andReturns([
            'hits' => [
                'total' => 5,
                'hits' => [
                    [
                        '_id' => 3,
                        '_source' => [
                            'id' => 1
                        ],
                        'sort' => [
                            3
                        ]
                    ],
                    [
                        '_id' => 2,
                        '_source' => [
                            'id' => 2
                        ],
                        'sort' => [
                            2
                        ]
                    ],
                    [
                        '_id' => 1,
                        '_source' => [
                            'id' => 3
                        ],
                        'sort' => [
                            1
                        ]
                    ],
                ]
            ],
        ]);

        $builder = new Builder($modelMock, 'mustang');
        $builder->orderBy('id');
        /**
         * @var CursorPaginator $paginator
         */
        $paginator = $this->engine->cursorPaginate($builder, $perPage, 'cursor', $cursor);

        $this->assertEquals(3, $paginator->nextCursor()->parameter('id'));
        $this->assertNull($paginator->previousCursor());

        return $paginator->previousCursor();
    }

    public function test_order_by_raw()
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
                'sort' => [
                    [
                        'rating' => [
                            'order' => 'desc',
                            'mode' => 'avg'
                        ]
                    ]
                ],
                'size' => $perPage,
                'from' => ($page - 1) * $perPage,
            ]
        ]);

        $builder = new Builder(new TestModel(), 'mustang');
        $builder->orderByRaw(
            new FieldSort('rating', 'desc', null, ['mode' => 'avg'])
        );

        $this->engine->paginate($builder, $perPage, $page);
    }

}
