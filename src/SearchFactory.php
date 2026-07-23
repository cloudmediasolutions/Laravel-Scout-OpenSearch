<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch;

use Illuminate\Pagination\Cursor;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\QueryStringQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Search;
use OpenSearchDSL\Sort\FieldSort;

final class SearchFactory
{
    public static function create(Builder $builder, array $options = [], ?Cursor $cursor = null): Search
    {
        $search = new Search;

        $query = $builder->query ? new QueryStringQuery($builder->query) : null;

        if (self::hasWhereFilters($builder)) {
            $boolQuery = new BoolQuery;
            self::addFilters($builder, $boolQuery);

            if ($query) {
                $boolQuery->add($query);
            }

            $search->addQuery($boolQuery);
        } elseif ($query) {
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
            $search = self::addOrders($builder, $cursor, $search);
        }

        return $search;
    }

    private static function hasWhereFilters(Builder $builder): bool
    {
        return ! empty($builder->wheres)
            || ! empty($builder->whereIns)
            || ! empty($builder->whereNotIns);
    }

    private static function addFilters(Builder $builder, BoolQuery $boolQuery): void
    {
        foreach ($builder->wheres as $field => $value) {
            $where = is_array($value) && isset($value['field'], $value['operator'])
                ? $value
                : ['field' => $field, 'operator' => '=', 'value' => $value];

            [$query, $type] = self::whereQuery(
                (string) $where['field'],
                (string) $where['operator'],
                $where['value'],
            );

            $boolQuery->add($query, $type);
        }

        foreach ($builder->whereIns as $field => $values) {
            $boolQuery->add(new TermsQuery((string) $field, $values), BoolQuery::FILTER);
        }

        foreach ($builder->whereNotIns as $field => $values) {
            $boolQuery->add(new TermsQuery((string) $field, $values), BoolQuery::MUST_NOT);
        }
    }

    private static function whereQuery(string $field, string $operator, mixed $value): array
    {
        return match ($operator) {
            '=' => [new TermQuery($field, $value), BoolQuery::FILTER],
            '!=' => [new TermQuery($field, $value), BoolQuery::MUST_NOT],
            '<' => [new RangeQuery($field, [RangeQuery::LT => $value]), BoolQuery::FILTER],
            '<=' => [new RangeQuery($field, [RangeQuery::LTE => $value]), BoolQuery::FILTER],
            '>' => [new RangeQuery($field, [RangeQuery::GT => $value]), BoolQuery::FILTER],
            '>=' => [new RangeQuery($field, [RangeQuery::GTE => $value]), BoolQuery::FILTER],
            default => throw new InvalidArgumentException("Unsupported Scout where operator [{$operator}]."),
        };
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
