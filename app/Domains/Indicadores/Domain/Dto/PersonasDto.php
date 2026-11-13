<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `personas` (spec indicadores, design D2). The activity of a person is the last access token
 * API-Auth issued to them (login or refresh). `inactivas30` and `inactivas90` count active people
 * with some activity older than that; `sinActividad`, the active people without any token.
 */
final readonly class PersonasDto
{
    /**
     * @param  list<IngresosDelDiaDto>  $ingresosPorDia  The last 30 days, oldest first, days without logins included
     */
    public function __construct(
        public int $activas,
        public int $deBaja,
        public int $inactivas30,
        public int $inactivas90,
        public int $sinActividad,
        public array $ingresosPorDia,
        public int $sesionesAbiertas,
        public int $clavesTemporalesVencidas,
        public int $soloClave,
    ) {}
}
