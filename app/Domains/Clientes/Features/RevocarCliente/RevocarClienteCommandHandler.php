<?php

namespace App\Domains\Clientes\Features\RevocarCliente;

use App\Domains\Clientes\Domain\ClienteDeIamAdminException;
use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use case: revoke an OAuth client, so API-Auth stops issuing and renewing its tokens (spec clientes, design
 * D3 of clientes-revocar). The change and its audit entry are one transaction. A client of iam-admin is
 * refused and the attempt audited; a client already revoked changes nothing and is not audited.
 */
final class RevocarClienteCommandHandler
{
    private const ACCION = 'cliente.revocacion';

    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $id): void
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server.
        $cliente = Str::isUuid($id) ? $this->clientes->buscar($id) : null;
        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        if ($cliente->esDeIamAdmin()) {
            $rechazo = new ClienteDeIamAdminException;
            $this->auditoria->rechazar(self::ACCION, 'cliente', $cliente->id, $rechazo->code());

            throw $rechazo;
        }

        if ($cliente->revocado) {
            return;
        }

        DB::transaction(function () use ($cliente) {
            if ($this->clientes->marcarRevocado($cliente->id, true)) {
                $this->auditoria->registrar(self::ACCION, 'cliente', $cliente->id, ['revocado' => false], ['revocado' => true]);
            }
        });
    }
}
