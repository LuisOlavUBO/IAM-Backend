<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * How many different people got a token on a day (`fecha` as Y-m-d, in the application's zone).
 */
final readonly class IngresosDelDiaDto
{
    public function __construct(
        public string $fecha,
        public int $personas,
    ) {}
}
