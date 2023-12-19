<?php

namespace CloudMediaSolutions\LaravelScoutOpenSearch\Providers;

use CloudMediaSolutions\LaravelScoutOpenSearch\Engines\OpenSearchEngine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use OpenSearchDSL\Sort\FieldSort;
use Aws\Credentials\CredentialProvider;

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

        $this->app->make(EngineManager::class)->extend(OpenSearchEngine::class, function (Application $app) {
            $opensearch = $app->make(Client::class);

            return new OpenSearchEngine($opensearch);
        });

        $this->app->singleton(Client::class, function () {
            // return ClientBuilder::fromConfig(config('opensearch.client'));
            return (new ClientBuilder())
                ->setHosts(config('opensearch.client.hosts'))
                ->setSigV4Region(config('opensearch.client.sigV4Region'))
                ->setSigV4Service(config('opensearch.client.sigV4Service'))
                ->setSigV4CredentialProvider(CredentialProvider::defaultProvider());
        });

        Builder::macro('cursorPaginate', function (int $perPage = null, string $cursorName = 'cursor', $cursor = null): CursorPaginator {
            /**
             * @var Builder $this
             */
            $perPage = $perPage ?: $this->model->getPerPage();

            return $this->engine()->cursorPaginate($this, $perPage, $cursorName, $cursor);
        });

        Builder::macro('orderByRaw', function (FieldSort $sort) {
            /**
             * @var Builder $this
             */
            $this->orders[] = $sort;

            return $this;
        });
    }
}
