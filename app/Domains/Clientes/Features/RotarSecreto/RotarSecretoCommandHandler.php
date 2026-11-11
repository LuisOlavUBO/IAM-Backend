<?php

namespace App\Domains\Clientes\Features\RotarSecreto;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\ClientePublicoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\SecretoDeCliente;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Use case: give a confidential client a new secret (spec clientes, design D3 and D4 of
 * clientes-rotar-secreto). Only its hash is saved, with an audit entry without values, in one transaction;
 * the previous secret stops validating in API-Auth at once. The secret lives only in memory and in the
 * response. Used by the portal and by iam:rotate-secret.
 */
final class RotarSecretoCommandHandler
{
    public const IAM_ADMIN = 'iam-admin';

    /** The variable of API-IAM's .env that holds the secret of each client of iam-admin. */
    private const VARIABLES_ENV = [
        'iam-admin-portal' => 'UBO_LOGIN_CLIENT_SECRET',
        'iam-admin-sistema' => 'UBO_AUTH_CLIENT_SECRET',
    ];

    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IAuditor $auditoria,
    ) {}

    /**
     * @param  string|null  $confirmar  The client's name, required for the clients of iam-admin
     */
    public function __invoke(string $id, ?string $confirmar = null): SecretoRotadoDto
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server.
        $cliente = Str::isUuid($id) ? $this->clientes->buscar($id) : null;
        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        if (! $cliente->confidencial) {
            throw new ClientePublicoException;
        }

        // A missing step, not an improper attempt: it is not audited.
        if ($cliente->aplicacion === self::IAM_ADMIN && $confirmar !== $cliente->nombre) {
            throw ValidationException::withMessages([
                'confirmar' => "Escriba el nombre del cliente ({$cliente->nombre}) para confirmar: API-IAM lo usa y dejará de funcionar hasta actualizar su .env.",
            ]);
        }

        $secreto = SecretoDeCliente::generar();

        DB::transaction(function () use ($cliente, $secreto) {
            $this->clientes->cambiarSecreto($cliente->id, $secreto->hash());
            $this->auditoria->registrar('cliente.secreto.rotacion', 'cliente', $cliente->id);
        });

        $variableEnv = $cliente->aplicacion === self::IAM_ADMIN ? (self::VARIABLES_ENV[$cliente->nombre] ?? null) : null;

        return new SecretoRotadoDto($secreto->secreto, $variableEnv);
    }
}
