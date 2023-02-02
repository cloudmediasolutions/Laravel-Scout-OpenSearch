<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Builders;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class OpensearchBuilder extends Builder
{
    public function orderByRaw(FieldSort $sort)
    {
        $this->orders[] = $sort;

        return $this;
    }

    public function cursorPaginate(
        int $perPage = null,
        string $cursorName = 'cursor',
        $cursor = null
    ) {
        $perPage = $perPage ?: $this->model->getPerPage();

        return $this->engine()->cursorPaginate(
            $this, 
            $perPage, 
            $cursorName, 
            $cursor
        );
    }
}