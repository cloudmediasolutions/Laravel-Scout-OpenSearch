<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Paginator;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

final class ScrollPaginator extends CursorPaginator
{
    private ?Cursor $nextCursor = null;
    private ?Cursor $previousCursor = null;

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
    ) {
        parent::__construct($items, $perPage, $cursor, $options);

        $this->initCursors(
            array_slice($response['hits']['hits'], 0, $perPage)
        );
    }

    private function initCursors(array $rawItems): void
    {
        if (! $this->onLastPage() && 
            count($rawItems) > 0
        ) {
            $nextItem = $this->pointsToPrevoiusItems()
                ? array_shift($rawItems)
                : array_pop($rawItems);
            
            $this->nextCursor = new Cursor(
                array_combine($this->parameters, $nextItem['sort'])
            );
        }

        if (! $this->onFirstPage() && 
            count($rawItems) > 0
        ) {
            $previousItem = $this->pointsToPrevoiusItems()
                ? array_pop($rawItems)
                : array_shift($rawItems);

            $this->previousCursor = new Cursor(
                array_combine($this->parameters, $previousItem['sort']), 
                false
            );
        }
    }

    private function pointsToPrevoiusItems(): bool
    {
        if (! $this->cursor) {
            return false;
        }

        return $this->cursor->pointsToPreviousItems();
    }

    /**
     * @inheritDoc
     */
    public function previousCursor()
    {
        return $this->previousCursor;
    }

    /**
     * @inheritDoc
     */
    public function nextCursor()
    {
        return $this->nextCursor;
    }
}
