<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Spec comandos-de-operacion: los comandos iam:* solo existen por consola.
 */
class SinRutaDeInstalacionTest extends TestCase
{
    public function test_no_route_installs_iam_or_grants_administration_roles(): void
    {
        $actions = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri().' '.$route->getActionName());

        foreach (['install', 'instal', 'grant', 'Command'] as $word) {
            $this->assertSame([], $actions->filter(fn (string $action) => str_contains(strtolower($action), strtolower($word)))->values()->all(), $word);
        }
    }

    public function test_the_commands_exist_in_the_console(): void
    {
        $this->artisan('iam:install', ['--help' => true])->assertSuccessful();
        $this->artisan('iam:grant-admin', ['--help' => true])->assertSuccessful();
    }
}
