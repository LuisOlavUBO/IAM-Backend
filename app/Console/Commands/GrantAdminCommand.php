<?php

namespace App\Console\Commands;

use App\Shared\Auditoria\IAuditor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emergency access (spec comandos-de-operacion): gives the iam-admin administrator role back to an
 * existing person when nobody can enter the portal. It never touches the password.
 */
#[Signature('iam:grant-admin
    {email : Email of an existing person}
    {--reactivar : Also re-enable the person when they are disabled}')]
#[Description('Acceso de emergencia: da el rol iam-admin/administrador a una persona existente')]
class GrantAdminCommand extends Command
{
    public function __construct(private readonly IAuditor $auditoria)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $person = DB::table('users')->where('email', $email)->first(['id', 'uuid', 'disabled_at']);

        if ($person === null) {
            $this->components->error("No existe una persona con el email {$email}. Este comando no crea personas.");

            return self::FAILURE;
        }

        $disabled = $person->disabled_at !== null;
        if ($disabled && ! $this->option('reactivar')) {
            $this->components->error("{$email} está dada de baja. Use --reactivar para dejarla activa y darle el rol.");

            return self::FAILURE;
        }

        $roleId = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('applications.name', 'iam-admin')->where('roles.name', 'administrador')
            ->value('roles.id');

        if ($roleId === null) {
            $this->components->error('No existe el rol iam-admin/administrador: IAM no está instalado (iam:install).');

            return self::FAILURE;
        }

        $hasRole = DB::table('role_user')->where('user_id', $person->id)->where('role_id', $roleId)->exists();
        if ($hasRole && ! $disabled) {
            $this->components->info("{$email} ya tiene el rol iam-admin/administrador: no se cambió nada.");

            return self::SUCCESS;
        }

        $connection = config('database.connections.'.config('database.default'));
        $this->components->twoColumnDetail('Ambiente', (string) app()->environment());
        $this->components->twoColumnDetail('Servidor', (string) ($connection['host'] ?? ''));
        $this->components->twoColumnDetail('Base de datos', (string) ($connection['database'] ?? ''));
        $this->components->twoColumnDetail('Persona', $email.($disabled ? ' (se reactivará)' : ''));

        if (! $this->confirm('¿Dar el rol iam-admin/administrador a esta persona?')) {
            $this->components->warn('Cancelado: no se cambió nada.');

            return self::FAILURE;
        }

        $uuid = Str::lower((string) $person->uuid);

        DB::transaction(function () use ($person, $roleId, $hasRole, $disabled, $uuid) {
            if ($disabled) {
                DB::table('users')->where('id', $person->id)->update(['disabled_at' => null, 'updated_at' => now()]);
                $this->auditoria->registrar('usuario.alta', 'usuario', $uuid, ['activo' => false], ['activo' => true]);
            }

            if (! $hasRole) {
                DB::table('role_user')->insert(['user_id' => $person->id, 'role_id' => $roleId]);
                $this->auditoria->registrar('usuario.rol.asignacion.emergencia', 'usuario', $uuid, null, ['rol' => 'iam-admin/administrador']);
            }
        });

        $this->components->info("{$email} tiene el rol iam-admin/administrador. Entrará con él en su próximo inicio de sesión.");

        return self::SUCCESS;
    }
}
