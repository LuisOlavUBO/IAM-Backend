<?php

namespace App\Domains\Indicadores\Features\VerIndicadores;

use App\Domains\Indicadores\Dto\AuditoriaDto;
use App\Domains\Indicadores\Dto\ClientesDto;
use App\Domains\Indicadores\Dto\EntornoDto;
use App\Domains\Indicadores\Dto\PersonasDto;
use App\Domains\Indicadores\Dto\PrivilegiosDto;
use App\Domains\Indicadores\Dto\SenalesDto;
use Carbon\CarbonImmutable;

/**
 * The blocks allowed to who asks, and when the oldest of them was computed (meta.calculado_en).
 */
final readonly class IndicadoresCalculadosDto
{
    /**
     * @param  array{entorno?: EntornoDto, personas?: PersonasDto, privilegios?: PrivilegiosDto, clientes?: ClientesDto, auditoria?: AuditoriaDto, senales?: SenalesDto}  $bloques
     */
    public function __construct(
        public array $bloques,
        public CarbonImmutable $calculadoEn,
    ) {}
}
