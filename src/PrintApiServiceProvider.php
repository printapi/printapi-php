<?php

namespace PrintApi;

use PrintApi\Client;
use Illuminate\Support\ServiceProvider;

class PrintApiServiceProvider extends BaseServiceProvider
{

    public function register()
    {
        $this->app->bind('printApi', function ($app) {
            return new PrintApi\Client();
        });
    }

    /**
     * Register the config for publishing
     *
     */
    public function boot()
    {

    }

}
