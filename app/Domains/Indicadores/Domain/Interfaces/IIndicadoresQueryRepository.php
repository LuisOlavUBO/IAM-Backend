<?php

namespace App\Domains\Indicadores\Domain\Interfaces;

use App\Domains\Indicadores\Domain\Dto\AuditoriaDto;
use App\Domains\Indicadores\Domain\Dto\ClientesDto;
use App\Domains\Indicadores\Domain\Dto\EntornoDto;
use App\Domains\Indicadores\Domain\Dto\PersonasDto;
use App\Domains\Indicadores\Domain\Dto\PrivilegiosDto;
use App\Domains\Indicadores\Domain\Dto\SenalesDto;

/**
 * Read repository of the indicators of the IAM environment (spec indicadores, design D1): one method
 * per block, each with a few aggregate queries. It reads tables of API-IAM and, only here, the
 * oauth_access_tokens and sessions of API-Auth. It never writes.
 */
interface IIndicadoresQueryRepository
{
    public function entorno(): EntornoDto;

    public function personas(): PersonasDto;

    public function privilegios(): PrivilegiosDto;

    public function clientes(): ClientesDto;

    public function auditoria(): AuditoriaDto;

    public function senales(): SenalesDto;
}
