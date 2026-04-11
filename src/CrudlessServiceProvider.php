<?php

namespace Laraditz\Crudless;

use Illuminate\Support\ServiceProvider;

class CrudlessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CrudlessManager::class, fn () => new CrudlessManager());
    }

    public function boot(): void
    {
        //
    }
}
