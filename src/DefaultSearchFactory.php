<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch;

use Laravel\Scout\Builder;
use Illuminate\Pagination\Cursor;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class DefaultSearchFactory implements SearchFactory
{
    public function create(Builder $builder, array $options = [], ?Cursor $cursor): Search
    {
        $search = new Search();

        $query = $builder->query ? new QueryStringQuery($builder->query) : null;

        $filters = $this->mappedWheres(
            array_merge($builder->wheres, $builder->whereIns)
        );

        if (!empty($filters)) {
            $boolQuery = new BoolQuery();

            foreach ($filters as $filter) {
                $boolQuery->add($filter, BoolQuery::FILTER);
            }

            if ($query) {
                $boolQuery->add($query);
            }
        }

        $sorts = $this->mappedSorts($builder);

        if ($cursor && $cursor->pointsToPreviousItems()) {
            array_walk($sorts, [$this, "invertOrders"]);
        }

        /**@var FieldSort $sort */
        foreach ($sorts as $sort) {
            $search->addSort($sort);
        }

        $this->setOptions($search, $options);

        return empty($boolQuery)
            ? $search->addQuery($query)
            : $search->addQuery($boolQuery);
    }

    /**
     * @param array $wheres
     * @return (TermsQuery|TermQuery)[]
     */
    protected function mappedWheres(array $wheres): array
    {
        $clauses = [];

        foreach ($wheres as $field => $value) {
            $clauses[] = is_array($value)
                ? new TermsQuery((string) $field, $value)
                : new TermQuery((string) $field, $value);
        }

        return $clauses;
    }

    /**
     * @param Builder $builder
     * @return FieldSort[]
     */
    protected function mappedSorts(Builder $builder): array
    {
        return array_map(
            function ($order) {
                return is_array($order)
                    ? new FieldSort($order['column'], $order['direction'])
                    : $order;
            },
            $builder->orders
        );
    }

    protected function invertOrders(FieldSort $sort): void
    {
        $direction = $sort->getOrder() ?? 'asc';

        $sort->setOrder(
            $direction === 'asc' ? 'desc' : 'asc'
        );
    }

    protected function setOptions(Search $search, array $options): void
    {
        if ($from = $options["from"] ?? null) {
            $search->setFrom($from);
        }

        if ($size = $options["size"] ?? null) {
            $search->setSize($size);
        }

        if ($searchAfter = $options["searchAfter"] ?? null) {
            $search->setSearchAfter($searchAfter);
        }
    }
}
