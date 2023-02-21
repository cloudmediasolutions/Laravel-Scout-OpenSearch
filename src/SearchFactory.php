<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch;

use Illuminate\Pagination\Cursor;
use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Search;

interface SearchFactory
{
    public function create(
        Builder $builder,
        array $options = [],
        ?Cursor $cursor
    ): Search;
}

