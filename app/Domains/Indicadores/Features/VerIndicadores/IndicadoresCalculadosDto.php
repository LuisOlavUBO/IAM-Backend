<?php

namespace App\Domains\Indicadores\Features\VerIndicadores;

use App\Domains\Indicadores\Domain\Dto\AuditoriaDto;
use App\Domains\Indicadores\Domain\Dto\ClientesDto;
use App\Domains\Indicadores\Domain\Dto\EntornoDto;
use App\Domains\Indicadores\Domain\Dto\PersonasDto;
use App\Domains\Indicadores\Domain\Dto\PrivilegiosDto;
use App\Domains\Indicadores\Domain\Dto\SenalesDto;
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
