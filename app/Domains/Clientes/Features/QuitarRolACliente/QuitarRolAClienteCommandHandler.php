<?php

namespace App\Domains\Clientes\Features\QuitarRolACliente;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\ReglasDeClientes;
use App\Domains\Clientes\Domain\RolNoAsignadoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: take a role from a service client (spec clientes, design D4 of clientes-roles-y-redirecciones).
 * Tokens already issued keep their scopes until they expire: the change rules from the next token. The
 * removal and its audit entry are one transaction.
 */
final class QuitarRolAClienteCommandHandler
{
    private const ACCION = 'cliente.rol.retiro';

    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $id, string $aplicacion, string $nombreRol): void
    {
        $cliente = Str::isUuid($id) ? $this->clientes->buscar($id) : null;
        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        try {
            ReglasDeClientes::verificarQueNoEsDeIamAdmin($cliente);
            ReglasDeClientes::verificarQueAdmiteRoles($cliente);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'cliente', $cliente->id, $rechazo->code());

            throw $rechazo;
        }

        $rol = $this->clientes->rol($aplicacion, $nombreRol);
        if ($rol === null || ! $this->clientes->tieneRol($cliente->id, $rol->id)) {
            throw new RolNoAsignadoException;
        }

        DB::transaction(function () use ($cliente, $rol) {
            $this->clientes->quitarRol($cliente->id, $rol->id);
            $this->auditoria->registrar(self::ACCION, 'cliente', $cliente->id, ['rol' => $rol->nombreCompleto()], null);
        });
    }
}
