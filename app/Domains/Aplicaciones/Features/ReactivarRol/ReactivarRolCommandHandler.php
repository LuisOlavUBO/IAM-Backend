<?php

namespace App\Domains\Aplicaciones\Features\ReactivarRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\RolNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;

/**
 * Use case: bring a retired role back (spec aplicaciones, "Dar de baja y reactivar un rol"; design D3 of
 * aplicaciones-baja). Who had it still has it, so it grants again from the next token. Reactivating an active
 * role changes nothing and is not audited.
 */
final class ReactivarRolCommandHandler
{
    private const ACCION = 'rol.alta';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre): void
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $rol = $this->catalogo->rol($app->id, $nombre) ?? throw new RolNoEncontradoException;

        DB::transaction(function () use ($rol) {
            if ($this->catalogo->cambiarBajaDeRol($rol->id, false)) {
                $this->auditoria->registrar(self::ACCION, 'rol', (string) $rol->id, ['activo' => false], ['activo' => true]);
            }
        });
    }
}
