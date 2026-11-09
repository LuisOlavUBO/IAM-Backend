<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema base de IAM: las tablas que hasta este punto creaban las migraciones de API-Auth.
 *
 * En una base vacía (tests, ambiente nuevo) crea cada tabla con su forma actual. En una base donde
 * API-Auth ya las creó no hace nada y solo queda registrada en iam_migrations. Desde aquí, el esquema
 * de estas tablas evoluciona con migraciones de API-IAM.
 *
 * Consolida estas migraciones de API-Auth (commit e7461a2), más account-lifecycle (users.disabled_at y
 * users.temporary_password_expires_at), cuya historia queda en el git de API-Auth:
 *   0001_01_01_000000_create_users_table (solo users)
 *   2026_10_20_203044_create_oauth_clients_table
 *   2026_10_21_155854_create_oauth_scopes_table
 *   2026_10_21_155855_create_roles_table
 *   2026_10_21_155857_add_role_id_to_users_table (users.role_id ya no existe)
 *   2026_10_21_172314_create_oauth_client_role_table
 *   2026_10_21_174559_create_apis_table
 *   2026_10_21_174602_add_api_id_to_oauth_scopes_table
 *   2026_10_30_163406_scope_roles_and_clients_to_applications
 *   2026_11_03_190148_add_uuid_to_users_table
 *   2026_11_03_192125_add_rut_to_users_table
 *   2026_11_04_194347_add_entra_oid_to_users_table
 *
 * Nombres, tipos y orden de columnas tomados de la base IAM de desarrollo (sys.indexes, sys.foreign_keys,
 * INFORMATION_SCHEMA.COLUMNS, 2026-11-09). El índice único de applications.name conserva el nombre
 * apis_name_unique, heredado del renombre de apis. Las claves primarias de users, applications,
 * oauth_scopes y roles tienen nombres generados por SQL Server (PK__tabla__hash), que no se reproducen:
 * ninguna migración debe referirse a ellas por nombre.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensureAccountLifecycleIsApplied();

        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique('apis_name_unique');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->uuid('uuid')->unique();
                $table->string('rut', 10)->nullable();
                $table->uuid('entra_oid')->nullable();
                $table->dateTime('disabled_at')->nullable();
                $table->dateTime('temporary_password_expires_at')->nullable();
            });

            DB::statement('CREATE UNIQUE INDEX users_rut_unique ON users (rut) WHERE rut IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX users_entra_oid_unique ON users (entra_oid) WHERE entra_oid IS NOT NULL');
        }

        if (! Schema::hasTable('oauth_scopes')) {
            Schema::create('oauth_scopes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('description');
                $table->timestamps();
                $table->foreignId('application_id')->constrained();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('description')->nullable();
                $table->timestamps();
                $table->foreignId('application_id')->constrained();
                $table->unique(['application_id', 'name']);
            });
        }

        if (! Schema::hasTable('oauth_clients')) {
            Schema::create('oauth_clients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->nullableMorphs('owner');
                $table->string('name');
                $table->string('secret')->nullable();
                $table->string('provider')->nullable();
                $table->text('redirect_uris');
                $table->text('grant_types');
                $table->boolean('revoked');
                $table->timestamps();
                $table->foreignId('application_id')->constrained();
            });
        }

        if (! Schema::hasTable('oauth_scope_role')) {
            Schema::create('oauth_scope_role', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('oauth_scope_id')->constrained('oauth_scopes')->cascadeOnDelete();
                $table->primary(['role_id', 'oauth_scope_id']);
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('oauth_client_role')) {
            Schema::create('oauth_client_role', function (Blueprint $table) {
                $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['client_id', 'role_id']);
            });
        }
    }

    /**
     * Reverse the migrations: the base schema is never reverted.
     */
    public function down(): void
    {
        throw new LogicException(
            'El esquema base de IAM no se revierte: sus tablas tienen los datos de IAM y de API-Auth depende de ellas.'
        );
    }

    /**
     * A users table created by API-Auth must already have the account-lifecycle columns.
     */
    private function ensureAccountLifecycleIsApplied(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $missing = array_values(array_filter(
            ['disabled_at', 'temporary_password_expires_at'],
            fn (string $column) => ! Schema::hasColumn('users', $column),
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'Falta users.'.implode(' y users.', $missing).'. Aplique primero las migraciones de API-Auth (account-lifecycle).'
            );
        }
    }
};
