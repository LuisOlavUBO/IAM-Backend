<?php

namespace App\Domains\Clientes\Features\CambiarRedirecciones;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\ReglasDeClientes;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: replace the redirections of a login client (spec clientes, design D4 of
 * clientes-roles-y-redirecciones). They are saved in the order received, without duplicates; the same list in
 * another order changes nothing and is not audited. The change and its audit entry are one transaction.
 */
final class CambiarRedireccionesCommandHandler
{
    private const ACCION = 'cliente.redirecciones.cambio';

    public function __construct(
        private readonly IClientesRepository $clientes,
        private readonly IAuditor $auditoria,
    ) {}

    /**
     * @param  list<string>  $redirecciones  Already validated: at least one, each a Redireccion
     * @return list<string> The client's redirections after the change
     */
    public function __invoke(string $id, array $redirecciones): array
    {
        $cliente = Str::isUuid($id) ? $this->clientes->buscar($id) : null;
        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        try {
            ReglasDeClientes::verificarQueNoEsDeIamAdmin($cliente);
            ReglasDeClientes::verificarQueAdmiteRedirecciones($cliente);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'cliente', $cliente->id, $rechazo->code());

            throw $rechazo;
        }

        $nuevas = array_values(array_unique($redirecciones));
        if (ReglasDeClientes::mismasRedirecciones($cliente->redirecciones, $nuevas)) {
            return $cliente->redirecciones;
        }

        DB::transaction(function () use ($cliente, $nuevas) {
            $this->clientes->cambiarRedirecciones($cliente->id, $nuevas);
            $this->auditoria->registrar(self::ACCION, 'cliente', $cliente->id, ['redirecciones' => $cliente->redirecciones], ['redirecciones' => $nuevas]);
        });

        return $nuevas;
    }
}
