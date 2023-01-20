<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Paginator;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

class ScrollPaginatorRaw extends CursorPaginator
{
    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param  \Illuminate\Pagination\Cursor|null  $cursor
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct(
        $items,
        $perPage,
        $cursor = null,
        $options = []
    )
    {
        parent::__construct($items, $perPage, $cursor, $options);
    }

    public function getCursorForItem($item, $isNext = true)
    {
        return new Cursor(array_combine($this->parameters, $item["sort"]), $isNext);
    }
}