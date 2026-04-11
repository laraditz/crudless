<?php

namespace Laraditz\Crudless\Facades;

use Illuminate\Support\Facades\Facade;
use Laraditz\Crudless\CrudlessManager;

/**
 * @method static void authRoutes(string $prefix = 'auth', string $controller = \Laraditz\Crudless\BaseAuthController::class, array $except = [])
 */
class Crudless extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CrudlessManager::class;
    }
}
