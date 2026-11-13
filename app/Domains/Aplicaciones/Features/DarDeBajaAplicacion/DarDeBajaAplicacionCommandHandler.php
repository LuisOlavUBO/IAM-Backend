<?php

namespace App\Domains\Aplicaciones\Features\DarDeBajaAplicacion;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\BajaSinConfirmarException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: retire a system (spec aplicaciones, "Dar de baja y reactivar una aplicación"; design D2 and D3 of
 * aplicaciones-baja). Without confirmation it answers with the impact; confirmed, it sets disabled_at and API-Auth
 * stops letting anyone in. Permissions, roles, clients and assignments stay. iam-admin is refused and the attempt
 * audited. Disabling it again changes nothing and is not audited.
 */
final class DarDeBajaAplicacionCommandHandler
{
    private const ACCION = 'aplicacion.baja';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $nombre, bool $confirmada): void
    {
        $app = $this->catalogo->aplicacion($nombre) ?? throw new AplicacionNoEncontradaException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($app->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'aplicacion', (string) $app->id, $rechazo->code());

            throw $rechazo;
        }

        if (! $confirmada) {
            throw new BajaSinConfirmarException($this->catalogo->impactoDeBajaDeAplicacion($app->id));
        }

        DB::transaction(function () use ($app) {
            if ($this->catalogo->cambiarBajaDeAplicacion($app->id, true)) {
                $this->auditoria->registrar(self::ACCION, 'aplicacion', (string) $app->id, ['activo' => true], ['activo' => false]);
            }
        });
    }
}
