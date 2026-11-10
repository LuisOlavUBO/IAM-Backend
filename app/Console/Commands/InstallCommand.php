<?php

namespace App\Console\Commands;

use App\Domains\Clientes\Domain\NuevoCliente;
use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Shared\Auditoria\IAuditor;
use App\Shared\Rut\Rut;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Installs IAM once per environment (spec comandos-de-operacion): the base catalog of api-auth, the
 * iam-admin application with its permissions and roles, the portal's and API-IAM's clients, and the
 * first administrator. Run by hand, never from the entrypoint or CI: it shows secrets once.
 */
#[Signature('iam:install
    {--admin-email= : Email of the first administrator}
    {--admin-nombre= : Name of the first administrator (only when the person does not exist yet)}
    {--admin-rut= : RUT of the first administrator (optional, only when the person does not exist yet)}
    {--admin-acceso=clave : "clave" (temporary password) or "microsoft" (no password)}
    {--redirect=* : Redirect URI of the portal client: <origin of the Angular app>/api/iam/v1/auth/callback (repeatable)}')]
#[Description('Instala IAM en este ambiente: catálogo base, aplicación iam-admin, clientes y primer administrador')]
class InstallCommand extends Command
{
    /** Base catalog of api-auth, as API-Auth's RoleScopeSeeder defines it. */
    private const API_AUTH_SCOPES = [
        'user:read' => 'Read the authenticated user profile',
        'user:write' => 'Update the authenticated user profile',
        'introspect' => 'Validate access tokens issued by this service',
        'sessions:revoke' => 'Close every session of a user (POST /v1/users/{uuid}/revoke)',
    ];

    private const API_AUTH_ROLES = [
        'user' => ['Basic user of api-auth', ['user:read', 'user:write']],
        'admin' => ['Administrators', ['user:read', 'user:write']],
        'introspector' => ['Services that validate user tokens through /introspect', ['introspect']],
        'session-revoker' => ['Services that close the sessions of a user (API-IAM)', ['sessions:revoke']],
    ];

    private const IAM_SCOPES = [
        'iam:usuarios.leer' => 'Ver usuarios y sus roles',
        'iam:usuarios.administrar' => 'Crear, editar, dar de baja y asignar roles a usuarios',
        'iam:aplicaciones.leer' => 'Ver aplicaciones, roles y permisos',
        'iam:aplicaciones.administrar' => 'Crear y editar aplicaciones, roles y permisos',
        'iam:clientes.leer' => 'Ver clientes OAuth',
        'iam:clientes.administrar' => 'Crear, rotar y revocar clientes OAuth',
        'iam:auditoria.leer' => 'Ver la auditoría de IAM',
    ];

    private const IAM_ROLES = [
        'administrador' => ['Administra IAM', ['iam:usuarios.leer', 'iam:usuarios.administrar', 'iam:aplicaciones.leer', 'iam:aplicaciones.administrar', 'iam:clientes.leer', 'iam:clientes.administrar', 'iam:auditoria.leer']],
        'mesa-de-ayuda' => ['Atiende a personas: altas, claves y roles', ['iam:usuarios.leer', 'iam:usuarios.administrar', 'iam:aplicaciones.leer']],
    ];

    public function __construct(private readonly IAuditor $auditoria, private readonly NuevoCliente $nuevoCliente)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->option('admin-email')));
        $redirects = array_values((array) $this->option('redirect'));
        $acceso = (string) $this->option('admin-acceso');
        $rut = $this->option('admin-rut') ? Rut::normalize((string) $this->option('admin-rut')) : null;
        $existing = $email === '' ? null : DB::table('users')->where('email', $email)->first(['id', 'uuid']);

        $errors = $this->validate($email, $redirects, $acceso, $rut, $existing === null);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if (DB::table('applications')->where('name', 'iam-admin')->exists()) {
            $this->components->error('IAM ya está instalado en esta base: la aplicación iam-admin existe. Para reparar algo use un comando específico.');

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        $this->components->twoColumnDetail('Ambiente', (string) app()->environment());
        $this->components->twoColumnDetail('Servidor', (string) ($connection['host'] ?? ''));
        $this->components->twoColumnDetail('Base de datos', (string) ($connection['database'] ?? ''));
        $this->components->twoColumnDetail('Primer administrador', $email.($existing ? ' (existente)' : ' (nuevo)'));

        if (! $this->confirm('¿Instalar IAM en esta base?')) {
            $this->components->warn('Instalación cancelada: no se escribió nada.');

            return self::FAILURE;
        }

        $result = DB::transaction(fn () => $this->install($email, $redirects, $acceso, $rut, $existing));

        $this->newLine();
        $this->components->info('IAM quedó instalado. Copie estas líneas al .env de API-IAM (se muestran una sola vez):');
        $this->line("UBO_LOGIN_CLIENT_ID={$result['portal']['id']}");
        $this->line("UBO_LOGIN_CLIENT_SECRET={$result['portal']['secreto']}");
        $this->line("UBO_AUTH_CLIENT_ID={$result['sistema']['id']}");
        $this->line("UBO_AUTH_CLIENT_SECRET={$result['sistema']['secreto']}");

        if ($result['clave'] !== null) {
            $this->newLine();
            $this->components->info("Contraseña temporal de {$email}, que vence el {$result['clave']->venceEn->format('Y-m-d H:i')} (se muestra una sola vez)");
            $this->line("CLAVE_TEMPORAL={$result['clave']->clave}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $redirects
     * @return list<string>
     */
    private function validate(string $email, array $redirects, string $acceso, ?string $rut, bool $isNew): array
    {
        $errors = [];

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $errors[] = 'Indique un --admin-email válido.';
        }

        if ($isNew && trim((string) $this->option('admin-nombre')) === '') {
            $errors[] = 'La persona no existe: indique --admin-nombre.';
        }

        if ($rut !== null && Validator::make(['rut' => $rut], ['rut' => new Rut])->fails()) {
            $errors[] = "El RUT {$rut} no es válido.";
        }

        if (! in_array($acceso, ['clave', 'microsoft'], true)) {
            $errors[] = '--admin-acceso debe ser "clave" o "microsoft".';
        }

        if ($redirects === []) {
            $errors[] = 'Indique al menos una --redirect (<origen del Angular>/api/iam/v1/auth/callback).';
        }

        foreach ($redirects as $redirect) {
            if (! self::isValidRedirect($redirect)) {
                $errors[] = "La redirección {$redirect} debe ser una URL absoluta https (o http en localhost), sin fragmento.";
            }
        }

        return $errors;
    }

    public static function isValidRedirect(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            return false;
        }

        $host = Str::lower($parts['host']);
        $local = $host === 'localhost' || str_ends_with($host, '.localhost');

        return Str::lower($parts['scheme']) === 'https' || (Str::lower($parts['scheme']) === 'http' && $local);
    }

    /**
     * @param  list<string>  $redirects
     * @return array{portal: array{id: string, secreto: string}, sistema: array{id: string, secreto: string}, clave: ?ClaveTemporal}
     */
    private function install(string $email, array $redirects, string $acceso, ?string $rut, ?object $existing): array
    {
        $apiAuth = $this->ensureApplication('api-auth', 'Authentication service (this application)');
        $this->ensureCatalog($apiAuth, self::API_AUTH_SCOPES, self::API_AUTH_ROLES);

        $iamAdmin = $this->ensureApplication('iam-admin', 'Portal de administración de IAM');
        $this->ensureCatalog($iamAdmin, self::IAM_SCOPES, self::IAM_ROLES);

        $portal = $this->nuevoCliente->crear($iamAdmin, 'iam-admin-portal', NuevoCliente::AUTHORIZATION_CODE, $redirects);
        $this->auditoria->registrar('cliente.creacion', 'cliente', $portal['id'], null, [
            'nombre' => 'iam-admin-portal', 'aplicacion' => 'iam-admin', 'tipo' => NuevoCliente::AUTHORIZATION_CODE, 'redirecciones' => $redirects,
        ]);

        $sistema = $this->nuevoCliente->crear($iamAdmin, 'iam-admin-sistema', NuevoCliente::CLIENT_CREDENTIALS);
        $this->auditoria->registrar('cliente.creacion', 'cliente', $sistema['id'], null, [
            'nombre' => 'iam-admin-sistema', 'aplicacion' => 'iam-admin', 'tipo' => NuevoCliente::CLIENT_CREDENTIALS,
        ]);

        foreach (['session-revoker', 'introspector'] as $role) {
            DB::table('oauth_client_role')->insert(['client_id' => $sistema['id'], 'role_id' => $this->roleId($apiAuth, $role)]);
            $this->auditoria->registrar('cliente.rol.asignacion', 'cliente', $sistema['id'], null, ['rol' => "api-auth/{$role}"]);
        }

        [$userId, $uuid, $clave] = $existing === null
            ? $this->createAdministrator($email, $acceso, $rut)
            : [(int) $existing->id, Str::lower((string) $existing->uuid), null];

        DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $this->roleId($iamAdmin, 'administrador')]);
        $this->auditoria->registrar('usuario.rol.asignacion', 'usuario', $uuid, null, ['rol' => 'iam-admin/administrador']);

        return ['portal' => $portal, 'sistema' => $sistema, 'clave' => $clave];
    }

    /**
     * @return array{0: int, 1: string, 2: ?ClaveTemporal}
     */
    private function createAdministrator(string $email, string $acceso, ?string $rut): array
    {
        $uuid = Str::lower((string) Str::uuid7());
        $nombre = trim((string) $this->option('admin-nombre'));
        $clave = $acceso === 'clave' ? ClaveTemporal::generar() : null;

        $userId = DB::table('users')->insertGetId([
            'uuid' => $uuid,
            'name' => $nombre,
            'email' => $email,
            'rut' => $rut,
            'created_at' => now(),
            'updated_at' => now(),
            ...($clave?->columnas() ?? []),
        ]);

        $this->auditoria->registrar('usuario.creacion', 'usuario', $uuid, null, [
            'nombre' => $nombre, 'correo' => $email, 'rut' => $rut, 'acceso' => $acceso,
        ]);

        return [(int) $userId, $uuid, $clave];
    }

    private function ensureApplication(string $name, string $description): int
    {
        $id = DB::table('applications')->where('name', $name)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $id = (int) DB::table('applications')->insertGetId(['name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        $this->auditoria->registrar('aplicacion.creacion', 'aplicacion', (string) $id, null, ['nombre' => $name, 'descripcion' => $description]);

        return $id;
    }

    /**
     * Adds the scopes, roles and role grants that are missing. Never removes or changes existing ones.
     *
     * @param  array<string, string>  $scopes
     * @param  array<string, array{0: string, 1: list<string>}>  $roles
     */
    private function ensureCatalog(int $applicationId, array $scopes, array $roles): void
    {
        $application = (string) DB::table('applications')->where('id', $applicationId)->value('name');

        foreach ($scopes as $name => $description) {
            if (! DB::table('oauth_scopes')->where('name', $name)->exists()) {
                $id = DB::table('oauth_scopes')->insertGetId(['application_id' => $applicationId, 'name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
                $this->auditoria->registrar('permiso.creacion', 'permiso', (string) $id, null, ['nombre' => $name, 'aplicacion' => $application, 'descripcion' => $description]);
            }
        }

        foreach ($roles as $name => [$description, $grants]) {
            if (! DB::table('roles')->where('application_id', $applicationId)->where('name', $name)->exists()) {
                $id = DB::table('roles')->insertGetId(['application_id' => $applicationId, 'name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
                $this->auditoria->registrar('rol.creacion', 'rol', (string) $id, null, ['nombre' => "{$application}/{$name}", 'descripcion' => $description]);
            }

            $roleId = $this->roleId($applicationId, $name);

            foreach ($grants as $scope) {
                $scopeId = (int) DB::table('oauth_scopes')->where('name', $scope)->value('id');

                if (! DB::table('oauth_scope_role')->where('role_id', $roleId)->where('oauth_scope_id', $scopeId)->exists()) {
                    DB::table('oauth_scope_role')->insert(['role_id' => $roleId, 'oauth_scope_id' => $scopeId]);
                    $this->auditoria->registrar('rol.permiso.asignacion', 'rol', (string) $roleId, null, ['permiso' => $scope]);
                }
            }
        }
    }

    private function roleId(int $applicationId, string $name): int
    {
        return (int) DB::table('roles')->where('application_id', $applicationId)->where('name', $name)->value('id');
    }
}
