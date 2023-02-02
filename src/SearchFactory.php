<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

final class SearchFactory
{
    public static function create(Builder $builder, array $options = []): Search
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
            /**
             * @var array|FieldSort $order
             */
            foreach ($builder->orders as $order) {
                $search->addSort(
                    is_array($order) 
                    ? new FieldSort($order['column'], $order['direction'])
                    : $order
                );
            }
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
}
