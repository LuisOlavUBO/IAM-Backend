<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Spec comandos-de-operacion (clientes-rotar-secreto): rotación de secreto por consola.
 */
class RotateSecretCommandTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = '¿Rotar el secreto de este cliente? El secreto anterior deja de servir de inmediato.';

    private const PORTAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const HONORARIOS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const FUAS_BACKEND = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private const PUBLICO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a04';

    private const SECRETO_ANTERIOR = 'secreto-anterior';

    /** What Str::random gives during these tests, so the printed secret is known. */
    private const SECRETO_NUEVO = 'Nuevo0123456789Nuevo0123456789Nuevo01234';

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $hash = Hash::make(self::SECRETO_ANTERIOR);

        foreach ([
            [self::PORTAL, $iamAdmin, 'iam-admin-portal', $hash],
            [self::HONORARIOS, $honorarios, 'backend', $hash],
            [self::FUAS_BACKEND, $fuas, 'backend', $hash],
            [self::PUBLICO, $fuas, 'fuas-spa', null],
        ] as [$id, $aplicacion, $nombre, $secreto]) {
            DB::table('oauth_clients')->insert([
                'id' => $id, 'application_id' => $aplicacion, 'name' => $nombre, 'secret' => $secreto,
                'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
            ]);
        }

        Str::createRandomStringsUsing(fn (int $largo) => substr(self::SECRETO_NUEVO, 0, $largo));
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    private function secret(string $id): ?string
    {
        return DB::table('oauth_clients')->where('id', $id)->value('secret');
    }

    private function sigueIgual(string $id): void
    {
        $this->assertTrue(Hash::check(self::SECRETO_ANTERIOR, (string) $this->secret($id)));
    }

    /** The new secret validates against the stored hash, the previous one does not, and it was audited. */
    private function assertRotado(string $id): void
    {
        $hash = (string) $this->secret($id);
        $this->assertTrue(Hash::check(self::SECRETO_NUEVO, $hash));
        $this->assertFalse(Hash::check(self::SECRETO_ANTERIOR, $hash));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.secreto.rotacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame($id, $entrada->entidad_id);
        $this->assertSame('comando', $entrada->actor_tipo);
        $this->assertNull($entrada->actor_uuid);
        $filas = json_encode(DB::table('iam_auditoria')->get()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString(self::SECRETO_NUEVO, $filas);
        $this->assertStringNotContainsString($hash, $filas);
    }

    public function test_the_portal_client_is_recovered_by_name_with_the_env_line(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => 'iam-admin-portal'])
            ->expectsOutputToContain('IAM_test')
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->expectsOutput('UBO_LOGIN_CLIENT_SECRET='.self::SECRETO_NUEVO)
            ->assertSuccessful();

        $this->assertRotado(self::PORTAL);
    }

    public function test_another_client_is_rotated_by_id_with_client_id_and_secret(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => strtoupper(self::HONORARIOS)])
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->expectsOutput('client_id='.self::HONORARIOS)
            ->expectsOutput('client_secret='.self::SECRETO_NUEVO)
            ->doesntExpectOutputToContain('UBO_')
            ->assertSuccessful();

        $this->assertRotado(self::HONORARIOS);
        $this->sigueIgual(self::FUAS_BACKEND);
    }

    public function test_a_refused_confirmation_changes_nothing_and_shows_no_secret(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => 'iam-admin-portal'])
            ->expectsConfirmation(self::QUESTION, 'no')
            ->doesntExpectOutputToContain(self::SECRETO_NUEVO)
            ->assertFailed();

        $this->sigueIgual(self::PORTAL);
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_ambiguous_name_lists_the_ids_and_changes_nothing(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => 'backend'])
            ->expectsOutputToContain(self::FUAS_BACKEND.' · fuas')
            ->expectsOutputToContain(self::HONORARIOS.' · pago-honorarios')
            ->assertFailed();

        $this->sigueIgual(self::HONORARIOS);
        $this->sigueIgual(self::FUAS_BACKEND);
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_unknown_name_or_id_fails(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => 'no-existe'])->assertFailed();
        $this->artisan('iam:rotate-secret', ['cliente' => '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'])->assertFailed();

        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_public_client_fails_without_a_secret(): void
    {
        $this->artisan('iam:rotate-secret', ['cliente' => 'fuas-spa'])
            ->expectsOutputToContain('público')
            ->assertFailed();

        $this->assertNull($this->secret(self::PUBLICO));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }
}
