<?php

namespace Laraditz\Crudless;

use Illuminate\Support\Facades\Route;

class CrudlessManager
{
    public function authRoutes(
        string $prefix = 'auth',
        string $controller = BaseAuthController::class,
        array $except = [],
        array $middleware = []
    ): void {
        $group = Route::prefix($prefix)->name("{$prefix}.");

        if ($middleware) {
            $group = $group->middleware($middleware);
        }

        $group->group(function () use ($controller, $except) {
            if (!in_array('register', $except)) {
                Route::post('register', [$controller, 'register'])->name('register');
            }

            if (!in_array('login', $except)) {
                Route::post('login', [$controller, 'login'])->name('login');
            }

            if (!in_array('logout', $except)) {
                Route::post('logout', [$controller, 'logout'])->middleware('auth:sanctum')->name('logout');
            }
        });
    }
}
