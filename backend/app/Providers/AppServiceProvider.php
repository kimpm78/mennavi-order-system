<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE SCHEMA IF NOT EXISTS public');
            DB::statement('SET search_path TO public');
        } catch (\Throwable) {
            //
        }
    }
}
