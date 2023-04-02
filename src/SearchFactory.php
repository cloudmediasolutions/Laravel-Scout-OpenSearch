<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch;

use Illuminate\Pagination\Cursor;
use Laravel\Scout\Builder;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\QueryStringQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Search;
use OpenSearchDSL\Sort\FieldSort;

final class SearchFactory
{
    public static function create(Builder $builder, array $options = [], Cursor $cursor = null): Search
    {
        $search = new Search();

        $query = $builder->query ? new QueryStringQuery($builder->query) : null;

        if (static::hasWhereFilters($builder)) {
            $boolQuery = new BoolQuery();
            $boolQuery = static::addWheres($builder, $boolQuery);
            $boolQuery = static::addWhereIns($builder, $boolQuery);

            if ($query) {
                $boolQuery->add($query);
            }
            $search->addQuery($boolQuery);
        } elseif($query) {
            $search->addQuery($query);
        }

        if (array_key_exists('from', $options)) {
            $search->setFrom($options['from']);
        }

        if (array_key_exists('size', $options)) {
            $search->setSize($options['size']);
        }

        if (array_key_exists('searchAfter', $options)) {
            $search->setSearchAfter($options['searchAfter']);
        }

        if (! empty($builder->orders)) {
            $search = static::addOrders($builder, $cursor, $search);
        }

        return $search;
    }

    private static function hasWhereFilters(Builder $builder): bool
    {
        return static::hasWheres($builder) || static::hasWhereIns($builder);
    }

    private static function hasWheres(Builder $builder): bool
    {
        return ! empty($builder->wheres);
    }

    private static function hasWhereIns(Builder $builder): bool
    {
        return ! empty($builder->whereIns);
    }

    private static function addWheres(Builder $builder, BoolQuery $boolQuery): BoolQuery
    {
        if (static::hasWheres($builder)) {
            foreach ($builder->wheres as $field => $value) {
                $boolQuery->add(new TermQuery((string) $field, $value), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    private static function addWhereIns($builder, $boolQuery): BoolQuery
    {
        if (static::hasWhereIns($builder)) {
            foreach ($builder->whereIns as $field => $arrayOfValues) {
                $boolQuery->add(new TermsQuery((string) $field, $arrayOfValues), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    private static function addOrders(Builder $builder, ?Cursor $cursor, Search $search): Search
    {
        $sorts = array_map(
            function ($order) use ($cursor) {
                $sort = is_array($order) 
                    ? new FieldSort($order['column'], $order['direction']) 
                    : $order;

                $direction = $sort->getOrder() ?? 'asc';

                if ($cursor && $cursor->pointsToPreviousItems()) {
                    $direction = $direction === 'asc' ? 'desc' : 'asc';
                    $sort->setOrder($direction);
                }

                return $sort;
            },
            $builder->orders
        );

        foreach ($sorts as $sort) {
            $search->addSort($sort);
        }

        return $search;
    }
}
