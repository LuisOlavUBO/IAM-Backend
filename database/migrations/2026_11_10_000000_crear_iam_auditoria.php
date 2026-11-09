<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de las acciones de administración de IAM (spec auditoria). Sin FK: una entrada
 * sobrevive a la entidad que describe, y nadie la modifica ni la borra.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('iam_auditoria', function (Blueprint $table) {
            $table->id();
            $table->dateTime('ocurrido_en', precision: 7);
            $table->string('actor_tipo', 20);
            $table->uuid('actor_uuid')->nullable();
            $table->string('accion', 100);
            $table->string('entidad_tipo', 50);
            $table->string('entidad_id', 100);
            $table->string('resultado', 20);
            $table->string('motivo', 100)->nullable();
            $table->longText('antes')->nullable();
            $table->longText('despues')->nullable();
            $table->char('trace_id', 32)->nullable();

            $table->index(['entidad_tipo', 'entidad_id', 'ocurrido_en']);
            $table->index(['actor_uuid', 'ocurrido_en']);
            $table->index('ocurrido_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iam_auditoria');
    }
};
