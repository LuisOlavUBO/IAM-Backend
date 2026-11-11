<?php

namespace App\Console\Commands;

use App\Domains\Clientes\Domain\Dto\ClienteParaRotarDto;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Features\RotarSecreto\RotarSecretoCommandHandler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Recovery of a lost secret (spec comandos-de-operacion, design D5 of clientes-rotar-secreto): rotates the
 * secret of a confidential client with the same use case as the portal, which is how the portal's own
 * client (iam-admin-portal) gets back in. The new secret is printed once and never logged.
 */
#[Signature('iam:rotate-secret
    {cliente : Name or id of the OAuth client}')]
#[Description('Rota el secreto de un cliente OAuth confidencial y lo muestra una sola vez')]
class RotateSecretCommand extends Command
{
    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly RotarSecretoCommandHandler $rotar,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cliente = $this->buscar(trim((string) $this->argument('cliente')));
        if ($cliente === null) {
            return self::FAILURE;
        }

        if (! $cliente->confidencial) {
            $this->components->error("El cliente {$cliente->nombre} es público (PKCE): no tiene secreto que rotar.");

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        $this->components->twoColumnDetail('Ambiente', (string) app()->environment());
        $this->components->twoColumnDetail('Servidor', (string) ($connection['host'] ?? ''));
        $this->components->twoColumnDetail('Base de datos', (string) ($connection['database'] ?? ''));
        $this->components->twoColumnDetail('Cliente', "{$cliente->nombre} ({$cliente->aplicacion})");
        $this->components->twoColumnDetail('Id', $cliente->id);

        if (! $this->confirm('¿Rotar el secreto de este cliente? El secreto anterior deja de servir de inmediato.')) {
            $this->components->warn('Cancelado: no se cambió nada.');

            return self::FAILURE;
        }

        // The command's confirmation is the one of environment and database: confirmar is the name itself.
        $rotado = ($this->rotar)($cliente->id, $cliente->nombre);

        $this->components->info('Secreto rotado. Cópielo ahora: no se vuelve a mostrar.');

        if ($rotado->variableEnv !== null) {
            $this->line("{$rotado->variableEnv}={$rotado->secreto}");
            $this->newLine();
            $this->components->warn("Actualice {$rotado->variableEnv} en el .env de API-IAM y reinícielo.");
        } else {
            $this->line("client_id={$cliente->id}");
            $this->line("client_secret={$rotado->secreto}");
        }

        return self::SUCCESS;
    }

    /**
     * The client by id when the argument is a UUID, otherwise by name; null (after saying why) when there is
     * none or the name is ambiguous.
     */
    private function buscar(string $cliente): ?ClienteParaRotarDto
    {
        if (Str::isUuid($cliente)) {
            $encontrado = $this->clientes->buscar($cliente);
            if ($encontrado === null) {
                $this->components->error("No existe un cliente con el id {$cliente}.");
            }

            return $encontrado;
        }

        $candidatos = $this->clientes->buscarPorNombre($cliente);

        if ($candidatos === []) {
            $this->components->error("No existe un cliente llamado {$cliente}.");

            return null;
        }

        if (count($candidatos) > 1) {
            $this->components->error("Hay más de un cliente llamado {$cliente}. Indique su id:");
            foreach ($candidatos as $candidato) {
                $this->line("  {$candidato->id} · {$candidato->aplicacion}");
            }

            return null;
        }

        return $candidatos[0];
    }
}
