<?php

namespace App\Domains\Indicadores\Features\VerIndicadores;

use App\Domains\Indicadores\Domain\Interfaces\IIndicadoresQueryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Use case: the indicators of the IAM environment for the portal's home (spec indicadores). Only the
 * blocks the permissions of who asks allow are computed (design D3), and each block is kept 5 minutes
 * in the cache with the moment it was computed (design D4).
 */
final class VerIndicadoresQueryHandler
{
    /** Block => the permission that shows it. */
    public const BLOQUES = [
        'entorno' => 'iam:aplicaciones.leer',
        'personas' => 'iam:usuarios.leer',
        'privilegios' => 'iam:usuarios.leer',
        'clientes' => 'iam:clientes.leer',
        'auditoria' => 'iam:auditoria.leer',
        'senales' => 'iam:usuarios.leer',
    ];

    public const SEGUNDOS_EN_CACHE = 300;

    public function __construct(private readonly IIndicadoresQueryRepository $indicadores) {}

    /**
     * @param  list<string>  $permisos  The permissions of who asks
     */
    public function __invoke(array $permisos): IndicadoresCalculadosDto
    {
        $bloques = [];
        $calculadoEn = null;

        foreach (self::BLOQUES as $bloque => $permiso) {
            if (! in_array($permiso, $permisos, true)) {
                continue;
            }

            /** @var array{calculado_en: string, datos: object} $guardado */
            $guardado = Cache::remember("indicadores.{$bloque}", self::SEGUNDOS_EN_CACHE, fn () => [
                'calculado_en' => CarbonImmutable::now()->toIso8601String(),
                'datos' => $this->indicadores->{$bloque}(),
            ]);

            $bloques[$bloque] = $guardado['datos'];
            $momento = CarbonImmutable::parse($guardado['calculado_en']);
            $calculadoEn = $calculadoEn === null || $momento->lessThan($calculadoEn) ? $momento : $calculadoEn;
        }

        return new IndicadoresCalculadosDto($bloques, $calculadoEn ?? CarbonImmutable::now());
    }
}
