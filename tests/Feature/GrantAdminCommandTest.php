<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Spec comandos-de-operacion: acceso de emergencia.
 */
class GrantAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = '¿Dar el rol iam-admin/administrador a esta persona?';

    private int $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $this->administrador = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'administrador']);
    }

    public function test_an_active_person_gets_the_administrator_role_and_it_is_audited(): void
    {
        $uuid = $this->person('ana@ubo.cl');
        $password = DB::table('users')->where('uuid', $uuid)->value('password');

        $this->artisan('iam:grant-admin', ['email' => 'ana@ubo.cl'])
            ->expectsOutputToContain('IAM_test')
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->assertSuccessful();

        $this->assertTrue($this->hasRole($uuid));
        $this->assertSame($password, DB::table('users')->where('uuid', $uuid)->value('password'));

        $entry = DB::table('iam_auditoria')->where('accion', 'usuario.rol.asignacion.emergencia')->sole();
        $this->assertSame('comando', $entry->actor_tipo);
        $this->assertSame($uuid, $entry->entidad_id);
        $this->assertSame('ok', $entry->resultado);
    }

    public function test_a_refused_confirmation_changes_nothing(): void
    {
        $uuid = $this->person('ana@ubo.cl');

        $this->artisan('iam:grant-admin', ['email' => 'ana@ubo.cl'])
            ->expectsConfirmation(self::QUESTION, 'no')
            ->assertFailed();

        $this->assertFalse($this->hasRole($uuid));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_disabled_person_is_refused_without_reactivar(): void
    {
        $uuid = $this->person('ana@ubo.cl', disabled: true);

        $this->artisan('iam:grant-admin', ['email' => 'ana@ubo.cl'])->assertFailed();

        $this->assertFalse($this->hasRole($uuid));
        $this->assertNotNull(DB::table('users')->where('uuid', $uuid)->value('disabled_at'));
    }

    public function test_reactivar_enables_the_person_and_gives_the_role(): void
    {
        $uuid = $this->person('ana@ubo.cl', disabled: true);

        $this->artisan('iam:grant-admin', ['email' => 'ana@ubo.cl', '--reactivar' => true])
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->assertSuccessful();

        $this->assertTrue($this->hasRole($uuid));
        $this->assertNull(DB::table('users')->where('uuid', $uuid)->value('disabled_at'));
        $this->assertSame(1, DB::table('iam_auditoria')->where('accion', 'usuario.alta')->count());
    }

    public function test_an_unknown_email_fails_and_creates_nobody(): void
    {
        $this->artisan('iam:grant-admin', ['email' => 'nadie@ubo.cl'])->assertFailed();

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_a_person_who_already_has_the_role_is_left_as_is(): void
    {
        $uuid = $this->person('ana@ubo.cl');
        DB::table('role_user')->insert(['user_id' => DB::table('users')->where('uuid', $uuid)->value('id'), 'role_id' => $this->administrador]);

        $this->artisan('iam:grant-admin', ['email' => 'ana@ubo.cl'])->assertSuccessful();

        $this->assertSame(1, DB::table('role_user')->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    private function person(string $email, bool $disabled = false): string
    {
        $uuid = Str::lower((string) Str::uuid7());
        DB::table('users')->insert([
            'uuid' => $uuid, 'name' => 'Persona', 'email' => $email, 'password' => Hash::make('definitiva'),
            'disabled_at' => $disabled ? now() : null,
        ]);

        return $uuid;
    }

    private function hasRole(string $uuid): bool
    {
        return DB::table('role_user')
            ->where('user_id', DB::table('users')->where('uuid', $uuid)->value('id'))
            ->where('role_id', $this->administrador)
            ->exists();
    }
}
