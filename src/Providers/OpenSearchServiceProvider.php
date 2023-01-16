<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Providers;

use CloudMediaSolutions\LaravelScoutOpenSearch\Engines\OpenSearchEngine;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/opensearch.php', 'opensearch');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/opensearch.php' => config_path('opensearch.php'),
        ], 'opensearch-config');

        $this->app->make(EngineManager::class)->extend(OpenSearchEngine::class, function () {
            $opensearch = app(Client::class);

            return new OpenSearchEngine($opensearch);
        });
        $this->app->bind(Client::class, function () {
            return ClientBuilder::fromConfig(config('opensearch.client'));
        });

        Builder::macro("cursorPaginate", function (int $perPage, string $cursorName): CursorPaginator {
            return $this->engine()->cursorPaginate($this, $perPage, $cursorName);
        });
    }
}