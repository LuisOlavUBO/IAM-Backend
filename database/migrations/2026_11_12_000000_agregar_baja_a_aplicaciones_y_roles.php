<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baja reversible de aplicaciones y roles (spec esquema-de-datos): `disabled_at` nula, que API-Auth lee al
 * dar acceso y emitir tokens (su cambio catalogo-dado-de-baja). Solo agrega columnas, y cada una solo si
 * falta, así que es inocua donde ya existen.
 */
return new class extends Migration
{
    private const TABLAS = ['applications', 'roles'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasColumn($tabla, 'disabled_at')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dateTime('disabled_at')->nullable());
            }
        }
    }

    /**
     * Reverse the migrations. Solo tiene sentido en `testing`: en los demás ambientes no se revierte.
     */
    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasColumn($tabla, 'disabled_at')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('disabled_at'));
            }
        }
    }
};
