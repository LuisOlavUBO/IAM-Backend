<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los permisos de Catálogo a Aplicaciones en las bases donde IAM ya está instalado (spec
 * esquema-de-datos). Cambia solo el nombre, así que el id y los roles que dan el permiso se conservan.
 * Es inocua en una base sin los nombres antiguos (vacía o instalada con la versión nueva de iam:install).
 */
return new class extends Migration
{
    private const NOMBRES = [
        'iam:catalogo.leer' => 'iam:aplicaciones.leer',
        'iam:catalogo.administrar' => 'iam:aplicaciones.administrar',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::NOMBRES as $antiguo => $nuevo) {
            $this->renombrar($antiguo, $nuevo);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::NOMBRES as $antiguo => $nuevo) {
            $this->renombrar($nuevo, $antiguo);
        }
    }

    private function renombrar(string $desde, string $hacia): void
    {
        if (DB::table('oauth_scopes')->where('name', $hacia)->exists()) {
            return;
        }

        DB::table('oauth_scopes')->where('name', $desde)->update(['name' => $hacia, 'updated_at' => now()]);
    }
};
