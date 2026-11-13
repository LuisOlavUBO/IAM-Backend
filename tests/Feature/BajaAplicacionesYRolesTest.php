<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migración que agrega la baja de aplicaciones y roles (spec esquema-de-datos). Los tests quitan las
 * columnas dentro de la transacción de RefreshDatabase, que en SQL Server revierte también el DDL.
 */
class BajaAplicacionesYRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_database_gets_disabled_at_in_null_and_nothing_else_changes(): void
    {
        $this->migracion()->down();
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'portal']);
        DB::table('roles')->insert(['application_id' => $aplicacion, 'name' => 'lector']);
        $antes = $this->filas();

        $this->migracion()->up();

        $this->assertTrue(Schema::hasColumn('applications', 'disabled_at'));
        $this->assertTrue(Schema::hasColumn('roles', 'disabled_at'));
        $this->assertSame(0, DB::table('applications')->whereNotNull('disabled_at')->count());
        $this->assertSame(0, DB::table('roles')->whereNotNull('disabled_at')->count());
        $this->assertEquals($antes, $this->filasSinBaja());
    }

    public function test_running_it_again_is_harmless(): void
    {
        $this->migracion()->up();

        $this->assertTrue(Schema::hasColumn('applications', 'disabled_at'));
        $this->assertTrue(Schema::hasColumn('roles', 'disabled_at'));
    }

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_11_12_000000_agregar_baja_a_aplicaciones_y_roles.php');
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function filas(): array
    {
        return [
            'applications' => DB::table('applications')->orderBy('id')->get()->all(),
            'roles' => DB::table('roles')->orderBy('id')->get()->all(),
        ];
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function filasSinBaja(): array
    {
        return array_map(
            fn (array $filas) => array_map(function (object $fila) {
                unset($fila->disabled_at);

                return $fila;
            }, $filas),
            $this->filas(),
        );
    }
}
