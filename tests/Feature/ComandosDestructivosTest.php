<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * La base IAM es compartida con API-Auth: los comandos que borran toda la base solo corren en testing
 * (spec esquema-de-datos, "Comandos destructivos prohibidos fuera de pruebas").
 */
class ComandosDestructivosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    public function test_migrate_fresh_is_refused_outside_testing(): void
    {
        $this->bootProviderIn('local');

        $this->artisan('migrate:fresh', ['--force' => true])->assertFailed();
        $this->artisan('db:wipe', ['--force' => true])->assertFailed();

        $this->assertTrue(Schema::hasTable('iam_migrations'));
    }

    public function test_destructive_commands_are_allowed_in_testing(): void
    {
        $this->bootProviderIn('testing');

        $this->assertFalse((new ReflectionProperty(FreshCommand::class, 'prohibitedFromRunning'))->getValue());
    }

    private function bootProviderIn(string $environment): void
    {
        $this->app['env'] = $environment;

        (new AppServiceProvider($this->app))->boot();
    }
}
