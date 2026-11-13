<?php

namespace App\Domains\Aplicaciones\Features\DarDeBajaRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\BajaSinConfirmarException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Domains\Aplicaciones\Domain\RolNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: retire a role (spec aplicaciones, "Dar de baja y reactivar un rol"; design D2 and D3 of
 * aplicaciones-baja). Without confirmation it answers with the impact (who has it and what it grants); confirmed,
 * it sets disabled_at and API-Auth stops granting it. The people and clients that have it and its permissions
 * stay. The roles of iam-admin are refused and the attempt audited. Disabling it again changes nothing.
 */
final class DarDeBajaRolCommandHandler
{
    private const ACCION = 'rol.baja';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre, bool $confirmada): void
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $rol = $this->catalogo->rol($app->id, $nombre) ?? throw new RolNoEncontradoException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($rol->aplicacion);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'rol', (string) $rol->id, $rechazo->code());

            throw $rechazo;
        }

        if (! $confirmada) {
            throw new BajaSinConfirmarException($this->catalogo->impactoDeRol($rol->id));
        }

        DB::transaction(function () use ($rol) {
            if ($this->catalogo->cambiarBajaDeRol($rol->id, true)) {
                $this->auditoria->registrar(self::ACCION, 'rol', (string) $rol->id, ['activo' => true], ['activo' => false]);
            }
        });
    }
}
