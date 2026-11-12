<?php

namespace App\Domains\Clientes\Features\AsignarRolACliente;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\ReglasDeClientes;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: give a role to a service client, so its client_credentials tokens carry the role's scopes from
 * the next one (spec clientes, design D4 of clientes-roles-y-redirecciones). Rule rejections are audited; the
 * change and its audit entry are one transaction.
 */
final class AsignarRolAClienteCommandHandler
{
    private const ACCION = 'cliente.rol.asignacion';

    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IAuditor $auditoria,
    ) {}

    /** True when the role was given; false when the client already had it (nothing changes). */
    public function __invoke(string $id, string $aplicacion, string $nombreRol): bool
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server.
        $cliente = Str::isUuid($id) ? $this->clientes->buscar($id) : null;
        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        $this->verificar($cliente->id, function () use ($cliente) {
            ReglasDeClientes::verificarQueNoEsDeIamAdmin($cliente);
            ReglasDeClientes::verificarQueAdmiteRoles($cliente);
        });

        $rol = $this->clientes->rol($aplicacion, $nombreRol)
            ?? throw ValidationException::withMessages(['rol' => 'No existe ese rol en esa aplicación.']);

        $this->verificar($cliente->id, fn () => ReglasDeClientes::verificarRolAsignable($rol));

        if ($this->clientes->tieneRol($cliente->id, $rol->id)) {
            return false;
        }

        DB::transaction(function () use ($cliente, $rol) {
            $this->clientes->asignarRol($cliente->id, $rol->id);
            $this->auditoria->registrar(self::ACCION, 'cliente', $cliente->id, null, ['rol' => $rol->nombreCompleto()]);
        });

        return true;
    }

    /** Runs a rule; a refusal is audited before it reaches the portal. */
    private function verificar(string $id, callable $regla): void
    {
        try {
            $regla();
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'cliente', $id, $rechazo->code());

            throw $rechazo;
        }
    }
}
