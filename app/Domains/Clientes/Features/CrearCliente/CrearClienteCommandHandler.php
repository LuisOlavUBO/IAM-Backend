<?php

namespace App\Domains\Clientes\Features\CrearCliente;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use App\Domains\Clientes\Domain\ClienteExistenteException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\NuevoCliente;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Use case: create a confidential client from the portal (spec clientes, design D4, D5 and D6 of
 * clientes-crear). The client and its audit entry are saved in one transaction; the secret lives only in
 * memory and in the response, never in the audit log nor the logs. The clients of iam-admin are created only
 * by iam:install.
 */
final class CrearClienteCommandHandler
{
    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IDirectorioAplicacionesService $aplicaciones,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(DatosNuevoClienteDto $datos): ClienteCreadoDto
    {
        $aplicacionId = $this->aplicaciones->idDeAplicacion($datos->aplicacion);
        // The name as stored, whatever case was written.
        $aplicacion = $aplicacionId === null ? null : ($this->aplicaciones->aplicaciones([$aplicacionId])[$aplicacionId] ?? null);

        if ($aplicacionId === null || $aplicacion === null) {
            throw ValidationException::withMessages(['aplicacion' => 'La aplicación no existe.']);
        }

        if ($aplicacion === 'iam-admin') {
            throw ValidationException::withMessages(['aplicacion' => 'Los clientes de iam-admin solo los crea iam:install.']);
        }

        if ($this->clientes->nombreEnUso($datos->nombre)) {
            throw new ClienteExistenteException;
        }

        $cliente = NuevoCliente::armar($datos->nombre, $datos->tipo, $datos->redirecciones);

        DB::transaction(function () use ($cliente, $aplicacionId, $aplicacion) {
            $this->clientes->crear($cliente, $aplicacionId);
            $this->auditoria->registrar('cliente.creacion', 'cliente', $cliente->id, null, [
                'nombre' => $cliente->nombre, 'aplicacion' => $aplicacion, 'tipo' => $cliente->tipo, 'redirecciones' => $cliente->redirecciones,
            ]);
        });

        return new ClienteCreadoDto($cliente->id, $cliente->secreto);
    }
}
