<?php

namespace App\Domains\Clientes\Features\ReactivarCliente;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use case: undo the revocation of an OAuth client; its unexpired tokens are valid again (spec clientes,
 * design D3 of clientes-revocar). The change and its audit entry are one transaction. Clients of iam-admin
 * may be reactivated, to recover one revoked by other means; an active client changes nothing and is not
 * audited.
 */
final class ReactivarClienteCommandHandler
{
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

        if (! $cliente->revocado) {
            return;
        }

        DB::transaction(function () use ($cliente) {
            if ($this->clientes->marcarRevocado($cliente->id, false)) {
                $this->auditoria->registrar('cliente.reactivacion', 'cliente', $cliente->id, ['revocado' => true], ['revocado' => false]);
            }
        });
    }
}
