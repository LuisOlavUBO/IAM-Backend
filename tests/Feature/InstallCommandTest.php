<?php

namespace Tests\Feature;

use App\Console\Commands\InstallCommand;
use App\Shared\Auditoria\AuditorBaseDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Spec comandos-de-operacion: iam:install.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'https://iam.ubo.cl/api/iam/v1/auth/callback';

    private const LOCAL_REDIRECT = 'http://localhost:4200/api/iam/v1/auth/callback';

    private const IAM_SCOPES = [
        'iam:auditoria.leer', 'iam:catalogo.administrar', 'iam:catalogo.leer', 'iam:clientes.administrar',
        'iam:clientes.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer',
    ];

    public function test_a_refused_confirmation_writes_nothing(): void
    {
        $this->install(confirm: false)->assertFailed();

        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_environment_and_database_are_shown_before_asking(): void
    {
        $this->artisan('iam:install', ['--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana', '--redirect' => [self::REDIRECT]])
            ->expectsOutputToContain('testing')
            ->expectsOutputToContain('IAM_test')
            ->expectsConfirmation('¿Instalar IAM en esta base?', 'no')
            ->assertFailed();
    }

    public function test_it_refuses_to_run_when_iam_admin_exists(): void
    {
        DB::table('applications')->insert(['name' => 'iam-admin']);

        $this->artisan('iam:install', ['--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana', '--redirect' => [self::REDIRECT]])
            ->expectsOutputToContain('ya está instalado')
            ->assertFailed();

        $this->assertSame(1, DB::table('applications')->count());
        $this->assertSame(0, DB::table('oauth_clients')->count());
    }

    public function test_a_new_database_gets_the_base_catalog_of_api_auth(): void
    {
        $this->install()->assertSuccessful();

        $apiAuth = (int) DB::table('applications')->where('name', 'api-auth')->value('id');
        $this->assertGreaterThan(0, $apiAuth);
        $this->assertEqualsCanonicalizing(
            ['user:read', 'user:write', 'introspect', 'sessions:revoke'],
            DB::table('oauth_scopes')->where('application_id', $apiAuth)->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['user', 'admin', 'introspector', 'session-revoker'],
            DB::table('roles')->where('application_id', $apiAuth)->pluck('name')->all(),
        );
        $this->assertSame(['sessions:revoke'], $this->scopesOf('api-auth', 'session-revoker'));
    }

    public function test_an_existing_api_auth_keeps_what_was_added_by_hand(): void
    {
        $apiAuth = DB::table('applications')->insertGetId(['name' => 'api-auth']);
        $admin = DB::table('roles')->insertGetId(['application_id' => $apiAuth, 'name' => 'admin']);
        $extra = DB::table('oauth_scopes')->insertGetId(['application_id' => $apiAuth, 'name' => 'extra:leer', 'description' => 'Agregado a mano']);
        DB::table('oauth_scope_role')->insert(['role_id' => $admin, 'oauth_scope_id' => $extra]);

        $this->install()->assertSuccessful();

        $this->assertSame(1, DB::table('applications')->where('name', 'api-auth')->count());
        $this->assertEqualsCanonicalizing(['extra:leer', 'user:read', 'user:write'], $this->scopesOf('api-auth', 'admin'));
    }

    public function test_the_portal_roles(): void
    {
        $this->install()->assertSuccessful();

        $this->assertEqualsCanonicalizing(self::IAM_SCOPES, $this->scopesOf('iam-admin', 'administrador'));
        $this->assertEqualsCanonicalizing(
            ['iam:usuarios.leer', 'iam:usuarios.administrar', 'iam:catalogo.leer'],
            $this->scopesOf('iam-admin', 'mesa-de-ayuda'),
        );
    }

    public function test_the_clients_of_api_iam(): void
    {
        $this->install(redirects: [self::REDIRECT, self::LOCAL_REDIRECT])->assertSuccessful();

        $iamAdmin = DB::table('applications')->where('name', 'iam-admin')->value('id');
        $portal = DB::table('oauth_clients')->where('name', 'iam-admin-portal')->sole();
        $sistema = DB::table('oauth_clients')->where('name', 'iam-admin-sistema')->sole();

        $this->assertEquals($iamAdmin, $portal->application_id);
        $this->assertSame(['authorization_code', 'refresh_token'], json_decode($portal->grant_types, true));
        $this->assertSame('users', $portal->provider);
        $this->assertSame([self::REDIRECT, self::LOCAL_REDIRECT], json_decode($portal->redirect_uris, true));

        $this->assertEquals($iamAdmin, $sistema->application_id);
        $this->assertSame(['client_credentials'], json_decode($sistema->grant_types, true));
        $this->assertEqualsCanonicalizing(
            ['session-revoker', 'introspector'],
            DB::table('oauth_client_role')->join('roles', 'roles.id', '=', 'oauth_client_role.role_id')
                ->where('client_id', $sistema->id)->pluck('roles.name')->all(),
        );
        $this->assertStringStartsWith('$2y$', $portal->secret);
        $this->assertStringStartsWith('$2y$', $sistema->secret);
    }

    public function test_invalid_redirects_fail_before_asking(): void
    {
        foreach ([[], ['/api/iam/v1/auth/callback'], ['http://iam.ubo.cl/api/iam/v1/auth/callback'], [self::REDIRECT.'#x']] as $redirects) {
            $this->artisan('iam:install', ['--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana', '--redirect' => $redirects])
                ->assertFailed();
        }

        $this->assertSame(0, DB::table('applications')->count());
    }

    public function test_a_new_administrator_with_a_temporary_password(): void
    {
        $this->freezeTime();

        $output = $this->installCapturingOutput(['--admin-email' => 'Ana@UBO.cl ', '--admin-nombre' => 'Ana Pérez', '--admin-rut' => '12.345.678-5']);

        $ana = DB::table('users')->where('email', 'ana@ubo.cl')->sole();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', strtolower($ana->uuid));
        $this->assertSame('Ana Pérez', $ana->name);
        $this->assertSame('12345678-5', $ana->rut);
        $this->assertStringStartsWith('$2y$', $ana->password);
        $this->assertLessThan(1, abs(now()->addHours(72)->diffInSeconds(Carbon::parse($ana->temporary_password_expires_at))));
        $this->assertSame(['administrador'], $this->rolesOf($ana->id, 'iam-admin'));

        $this->assertStringContainsString('Contraseña temporal de ana@ubo.cl', $output);
        preg_match('/^CLAVE_TEMPORAL=(\S+)/m', $output, $match);
        $this->assertTrue(Hash::check($match[1], $ana->password));
    }

    public function test_a_new_administrator_with_microsoft_has_no_password(): void
    {
        $output = $this->installCapturingOutput(['--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana', '--admin-acceso' => 'microsoft']);

        $ana = DB::table('users')->where('email', 'ana@ubo.cl')->sole();
        $this->assertNull($ana->password);
        $this->assertNull($ana->temporary_password_expires_at);
        $this->assertStringNotContainsString('Contraseña temporal', $output);
    }

    public function test_an_existing_person_keeps_their_password_and_data(): void
    {
        DB::table('users')->insert([
            'uuid' => (string) Str::uuid7(), 'name' => 'Ana Original', 'email' => 'ana@ubo.cl', 'password' => Hash::make('definitiva'),
        ]);
        $before = DB::table('users')->where('email', 'ana@ubo.cl')->sole();

        $this->install()->assertSuccessful();

        $after = DB::table('users')->where('email', 'ana@ubo.cl')->sole();
        $this->assertSame($before->password, $after->password);
        $this->assertSame('Ana Original', $after->name);
        $this->assertNull($after->temporary_password_expires_at);
        $this->assertSame(['administrador'], $this->rolesOf($after->id, 'iam-admin'));
    }

    public function test_a_failure_halfway_leaves_the_database_as_it_was_and_shows_no_secret(): void
    {
        $this->partialMock(AuditorBaseDatos::class, function ($mock) {
            $mock->shouldReceive('registrar')->andReturnUsing(function (string $accion) {
                if ($accion === 'usuario.rol.asignacion') {
                    throw new RuntimeException('falla a mitad de la instalación');
                }
            });
        });

        try {
            $this->install()->run();
            $this->fail('La instalación debió fallar.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('oauth_clients')->count());
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_a_successful_install_shows_the_env_lines_once_and_audits_no_secret(): void
    {
        $output = $this->installCapturingOutput();

        foreach (['UBO_LOGIN_CLIENT_ID', 'UBO_LOGIN_CLIENT_SECRET', 'UBO_AUTH_CLIENT_ID', 'UBO_AUTH_CLIENT_SECRET'] as $line) {
            $this->assertSame(1, substr_count($output, "{$line}="));
        }

        preg_match('/UBO_LOGIN_CLIENT_SECRET=(\S+)/', $output, $portal);
        preg_match('/UBO_AUTH_CLIENT_SECRET=(\S+)/', $output, $sistema);
        preg_match('/^CLAVE_TEMPORAL=(\S+)/m', $output, $clave);

        $audit = DB::table('iam_auditoria')->get()->map(fn ($e) => $e->antes.$e->despues)->implode("\n");
        $this->assertNotSame('', $audit);
        foreach ([$portal[1], $sistema[1], $clave[1], '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
        $this->assertSame(0, DB::table('iam_auditoria')->where('actor_tipo', '!=', 'comando')->count());
    }

    /**
     * @param  list<string>  $redirects
     */
    private function install(bool $confirm = true, array $redirects = [self::REDIRECT]): PendingCommand
    {
        return $this->artisan('iam:install', ['--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana Pérez', '--redirect' => $redirects])
            ->expectsConfirmation('¿Instalar IAM en esta base?', $confirm ? 'yes' : 'no');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function installCapturingOutput(array $options = []): string
    {
        // The real output (secrets included), answering "yes" to the confirmation.
        $input = new ArrayInput($options + [
            '--admin-email' => 'ana@ubo.cl', '--admin-nombre' => 'Ana Pérez', '--redirect' => [self::REDIRECT],
        ]);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "yes\n");
        rewind($stream);
        $input->setStream($stream);
        $output = new BufferedOutput;

        $command = $this->app->make(InstallCommand::class);
        $command->setLaravel($this->app);
        $exitCode = $command->run($input, $output);

        $text = $output->fetch();
        $this->assertSame(0, $exitCode, $text);

        return $text;
    }

    /**
     * @return list<string>
     */
    private function scopesOf(string $application, string $role): array
    {
        return DB::table('oauth_scope_role')
            ->join('roles', 'roles.id', '=', 'oauth_scope_role.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->join('oauth_scopes', 'oauth_scopes.id', '=', 'oauth_scope_role.oauth_scope_id')
            ->where('applications.name', $application)->where('roles.name', $role)
            ->pluck('oauth_scopes.name')->all();
    }

    /**
     * @return list<string>
     */
    private function rolesOf(int|string $userId, string $application): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('role_user.user_id', $userId)->where('applications.name', $application)
            ->pluck('roles.name')->all();
    }
}
