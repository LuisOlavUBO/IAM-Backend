<?php

namespace App\Domains\Aplicaciones\Features\ReactivarAplicacion;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;

/**
 * Use case: bring a retired system back (spec aplicaciones, "Dar de baja y reactivar una aplicación"; design D3
 * of aplicaciones-baja). Everything it had is still assigned, so it works again from the next token. Reactivating
 * an active application changes nothing and is not audited.
 */
final class ReactivarAplicacionCommandHandler
{
    private const ACCION = 'aplicacion.alta';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $nombre): void
    {
        $app = $this->catalogo->aplicacion($nombre) ?? throw new AplicacionNoEncontradaException;

        DB::transaction(function () use ($app) {
            if ($this->catalogo->cambiarBajaDeAplicacion($app->id, false)) {
                $this->auditoria->registrar(self::ACCION, 'aplicacion', (string) $app->id, ['activo' => false], ['activo' => true]);
            }
        });
    }
}
