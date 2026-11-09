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
        // La base IAM es compartida con API-Auth: migrate:fresh, migrate:refresh, migrate:reset y db:wipe
        // borrarían también sus tablas. Solo se permiten en la base de pruebas.
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));
    }
}
