<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * La migración esquema_base_iam (spec esquema-de-datos). Cada test corre dentro de la transacción de
 * RefreshDatabase: SQL Server revierte también el DDL, así que los estados de base que arma un test
 * (tablas borradas, columnas faltantes) desaparecen al terminar.
 */
class EsquemaBaseTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'users', 'applications', 'oauth_scopes', 'roles',
        'oauth_scope_role', 'role_user', 'oauth_client_role', 'oauth_clients',
    ];

    public function test_an_empty_database_gets_the_eight_iam_tables(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}.");
        }

        foreach ([
            'users' => ['uuid', 'rut', 'entra_oid', 'disabled_at', 'temporary_password_expires_at'],
            'oauth_scopes' => ['application_id'],
            'roles' => ['application_id'],
            'oauth_clients' => ['application_id'],
        ] as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, $columns), "Faltan columnas en {$table}.");
        }
    }

    public function test_rut_and_entra_oid_are_unique_only_when_present(): void
    {
        $this->insertUser();
        $this->insertUser();
        $this->insertUser(rut: '12345678-5');

        $this->expectException(QueryException::class);
        $this->insertUser(rut: '12345678-5');
    }

    public function test_a_role_name_is_unique_within_its_application(): void
    {
        $portal = $this->insertApplication('portal');
        $maestros = $this->insertApplication('api-maestros');

        DB::table('roles')->insert(['application_id' => $portal, 'name' => 'lector']);
        DB::table('roles')->insert(['application_id' => $maestros, 'name' => 'lector']);

        $this->expectException(QueryException::class);
        DB::table('roles')->insert(['application_id' => $portal, 'name' => 'lector']);
    }

    public function test_an_existing_schema_and_its_data_are_left_untouched(): void
    {
        $application = $this->insertApplication('api-auth');
        $this->insertUser(rut: '11111111-1');
        $before = [$this->schemaSnapshot(), $this->rowCounts()];

        $this->baseMigration()->up();

        $this->assertSame($before, [$this->schemaSnapshot(), $this->rowCounts()]);
        $this->assertSame('api-auth', DB::table('applications')->where('id', $application)->value('name'));
    }

    public function test_the_migration_fails_without_account_lifecycle_and_creates_nothing(): void
    {
        Schema::drop('oauth_client_role');
        Schema::table('users', fn ($table) => $table->dropColumn('temporary_password_expires_at'));

        try {
            $this->baseMigration()->up();
            $this->fail('La migración debió fallar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('account-lifecycle', $e->getMessage());
        }

        $this->assertFalse(Schema::hasTable('oauth_client_role'));
    }

    public function test_the_base_schema_cannot_be_rolled_back(): void
    {
        $this->insertUser();

        try {
            $this->baseMigration()->down();
            $this->fail('El rollback debió fallar.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('no se revierte', $e->getMessage());
        }

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_migrations_are_registered_in_iam_migrations(): void
    {
        $this->assertTrue(
            DB::table('iam_migrations')->where('migration', '2026_11_09_000000_esquema_base_iam')->exists()
        );
    }

    public function test_the_api_auth_migration_history_is_ignored_and_kept(): void
    {
        Schema::create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
        DB::table('migrations')->insert(['migration' => '2026_11_04_194347_add_entra_oid_to_users_table', 'batch' => 3]);

        $this->artisan('migrate:status')
            ->expectsOutputToContain('esquema_base_iam')
            ->doesntExpectOutputToContain('add_entra_oid_to_users_table')
            ->assertSuccessful();

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(1, DB::table('migrations')->count());
    }

    private function baseMigration(): Migration
    {
        return require database_path('migrations/2026_11_09_000000_esquema_base_iam.php');
    }

    private function insertApplication(string $name): int
    {
        return DB::table('applications')->insertGetId(['name' => $name]);
    }

    private function insertUser(?string $rut = null): void
    {
        DB::table('users')->insert([
            'uuid' => (string) Str::uuid7(),
            'name' => 'Persona',
            'email' => Str::random(10).'@ubo.cl',
            'rut' => $rut,
        ]);
    }

    /**
     * Columns and indexes of the IAM tables.
     *
     * @return array<int, array<string, mixed>>
     */
    private function schemaSnapshot(): array
    {
        $tables = "'".implode("','", self::TABLES)."'";

        return array_map(fn (object $row) => (array) $row, array_merge(
            DB::select("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
                FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME IN ({$tables}) ORDER BY TABLE_NAME, ORDINAL_POSITION"),
            DB::select("SELECT t.name AS table_name, i.name AS index_name, i.is_unique, i.filter_definition
                FROM sys.indexes i JOIN sys.tables t ON t.object_id = i.object_id
                WHERE t.name IN ({$tables}) AND i.name IS NOT NULL ORDER BY t.name, i.name"),
        ));
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }
}
