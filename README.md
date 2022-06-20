# OpenSearch Engine for Laravel Scout

This package provides an [OpenSearch](https://opensearch.org/) engine for [Laravel Scout](https://laravel.com/docs/9.x/scout). It's built on top of the latest release of Laravel Scout and lets you use OpenSearch as a driver for Scout. 

## Features
- Laravel Scout 9 support
- Fully configurable settings per index, with default settings support
- Optionally mappings configurable

## Requirements
- PHP >= 8.0
- Laravel >= 8

## Installation
You can include this package via Composer:

`composer require "cloudmediasolutions/laravel-scout-opensearch"`

Add / set environment variables (in .env):

`SCOUT_DRIVER=CloudMediaSolutions\LaravelScoutOpenSearch\OpenSearchEngine`

Add your OpenSearch host(s): (You can seperate multiple hosts with a comma)

`OPENSEARCH_HOSTS=http://localhost:9200`

## Usage


### Index settings
Some [index settings](https://opensearch.org/docs/latest/opensearch/rest-api/index-apis/create-index/#index-settings) are static and can only be set on index creation. That's why it is important to configure it - when you have specific whishes - before you start using an index. 

You can find an example in `opensearch.indices.default.settings`. Default is the key as default / fallback configuration. When you want a setting for a specific index, you use `opensearch.indices.YourIndexName.settings`.

### Mappings
Sometimes you need specific field mappings in OpenSearch. For example, when you use UUID's, the field type is automatically set to `text` and it can be usefull to have them as `keyword` in filters. 

You can find an example in `opensearch.indices.table.mappings`. Table is in this case your index name. 

### Search
You can search data as documented by Laravel in [their docs](https://laravel.com/docs/9.x/scout#searching). Because this search query uses query_string in the search query to OpenSearch, it is possible to execute complex queries, such as:

- Star Trek
- the wind AND (rises OR rising)
- status:active Pencil
