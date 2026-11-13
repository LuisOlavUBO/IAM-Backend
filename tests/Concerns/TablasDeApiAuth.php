<?php

namespace Tests\Concerns;

use DateTimeInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * oauth_access_tokens and sessions of API-Auth, which API-IAM only reads (indicadores-portal, D2).
 * No migration of API-IAM creates them: the test creates them in IAM_test with the shape API-Auth
 * gives them (Passport and Laravel's database sessions), inside the test's transaction.
 */
trait TablasDeApiAuth
{
    protected function crearTablasDeApiAuth(): void
    {
        if (! Schema::hasTable('oauth_access_tokens')) {
            Schema::create('oauth_access_tokens', function (Blueprint $table) {
                $table->char('id', 80)->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->uuid('client_id');
                $table->string('name')->nullable();
                $table->text('scopes')->nullable();
                $table->boolean('revoked');
                $table->timestamps();
                $table->dateTime('expires_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /** An access token API-Auth issued: to a person (user id) or, without one, to a service client. */
    protected function tokenEmitido(?int $userId, string $clientId, DateTimeInterface $emitido): void
    {
        DB::table('oauth_access_tokens')->insert([
            'id' => Str::random(80),
            'user_id' => $userId,
            'client_id' => $clientId,
            'revoked' => false,
            'created_at' => $emitido,
            'updated_at' => $emitido,
        ]);
    }

    /** A session of the central login. */
    protected function sesionAbierta(int $userId, string $ip, DateTimeInterface $ultimaActividad): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $userId,
            'ip_address' => $ip,
            'payload' => '',
            'last_activity' => $ultimaActividad->getTimestamp(),
        ]);
    }
}
