# OpenSearch Engine for Laravel Scout

This package provides an [OpenSearch](https://opensearch.org/) engine for [Laravel Scout](https://laravel.com/docs/9.x/scout). It's built on top of the latest release of Laravel Scout and lets you use OpenSearch as a driver for Scout. 

## Features
- Laravel Scout 9 support
- Fully configurable settings per index, with default settings support
- Optionally mappings configurable
- Cursor pagination

## Requirements
- PHP >= 8.0
- Laravel >= 8

## Installation
You can include this package via Composer:

`composer require "cloudmediasolutions/laravel-scout-opensearch"`

Add / set environment variables (in .env):

`SCOUT_DRIVER=CloudMediaSolutions\LaravelScoutOpenSearch\Engines\OpenSearchEngine`

Add your OpenSearch host(s): (You can seperate multiple hosts with a comma)

`OPENSEARCH_HOSTS=http://localhost:9200`

If you have any web authentication on your OpenSearch cluster, you can extend the `opensearch.client` config.

Basic authentication:
```php

    'client' => [
        'hosts' => explode(',', env('OPENSEARCH_HOSTS')),
        'basicAuthentication' => [
            env('OPENSEARCH_USERNAME'),
            env('OPENSEARCH_PASSWORD'),
        ],
    ],

```

## Usage

Before you can use custom index settings and mappings, you have to publish the config to your application:

`php artisan vendor:publish --tag "opensearch-config"`

After changing indexes you have to create the index:

If the index already exists, delete it first:

`php artisan scout:delete-index yourSearchableAsValue`

Then you can create the index:

`php artisan scout:index yourSearchableAsValue`

The index is at this point completely empty. You can import existing data as described in the Laravel Scout documentation: 

`php artisan scout:import "App\Models\Post"`

### Index settings
Some [index settings](https://opensearch.org/docs/latest/opensearch/rest-api/index-apis/create-index/#index-settings) are static and can only be set on index creation. That's why it is important to configure it - when you have specific whishes - before you start using an index. 

You can find an example in `opensearch.indices.default.settings`. Default is the key as default / fallback configuration. When you want a setting for a specific index, you use `opensearch.indices.yourSearchableAsValue.settings`.

### Mappings
Sometimes you need specific field mappings in OpenSearch. For example, when you use UUID's, the field type is automatically set to `text` and it can be usefull to have them as `keyword` in filters. 

You can find an example in `opensearch.indices.table.mappings`. Table is in this case your index name. 

### Search
You can search data as documented by Laravel in [their docs](https://laravel.com/docs/9.x/scout#searching). Because this search query uses query_string in the search query to OpenSearch, it is possible to execute complex queries, such as:

- Star Trek
- the wind AND (rises OR rising)
- status:active Pencil

### Cursor pagination
Cursor pagination uses [search_after](https://opensearch.org/docs/latest/opensearch/search/paginate#the-search_after-parameter) parameter pagination.

```php

    Song::search("crass")
        ->orderBy("_score", "desc")
        ->orderBy("id")
        ->cursorPaginate(10);

```
If no sorting provided, the _id field will be used as default, and therefore no relevance sorting can be applied when using cursor pagination.

Sort modes, nested and geo_distance sort are not supported yet.
