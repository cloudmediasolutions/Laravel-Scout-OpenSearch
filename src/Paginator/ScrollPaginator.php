<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Paginator;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;

class ScrollPaginator extends CursorPaginator
{
    private array $sortData = [];

    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param array $response
     * @param \Illuminate\Pagination\Cursor|null  $cursor
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct(
        $items,
        $perPage,
        array $response,
        $cursor = null,
        $options = []
    )
    {
        parent::__construct($items, $perPage, $cursor, $options);

        $items = $response['hits']['hits'];

        if (! $this->onFirstPage() && 
            $firstItem = array_shift($items)
        ) {
            $this->sortData[] = array_combine($this->parameters, $firstItem['sort']);
        }

        if (! $this->onLastPage() && 
            $lastItem = array_pop($items)
        ) {
            $this->sortData[] = array_combine($this->parameters, $lastItem['sort']);
        }
    }

    /**
     * @inheritDoc
     */
    public function previousCursor()
    {
        if ($this->onFirstPage()) {
            return null;
        }

        return $this->getCursorForItem(Arr::first($this->sortData), false);
    }

    /**
     * @inheritDoc
     */
    public function nextCursor()
    {
        if ($this->onLastPage()) {
            return null;
        }

        return $this->getCursorForItem(Arr::last($this->sortData), true);
    }
}
