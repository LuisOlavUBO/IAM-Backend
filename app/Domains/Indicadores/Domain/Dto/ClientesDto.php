<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `clientes` (spec indicadores, design D2 and D5). `login` and `servicio` count the clients
 * that are not revoked (by grant: authorization_code and client_credentials); service tokens are the
 * ones without a person.
 */
final readonly class ClientesDto
{
    /**
     * @param  list<TokensDeClienteDto>  $tokensPorCliente  The ten clients with most service tokens in 7 days
     * @param  list<ClienteSenaladoDto>  $sinUso30  Active service clients without tokens in 30 days (fecha: last token)
     * @param  list<ClienteSenaladoDto>  $sinRoles  Active service clients without roles (fecha: null)
     * @param  list<ClienteSenaladoDto>  $secretosSinRotar  Active confidential clients whose secret is older than 180 days (fecha: last rotation or creation)
     */
    public function __construct(
        public int $login,
        public int $servicio,
        public int $revocados,
        public int $tokens24h,
        public int $tokens7d,
        public array $tokensPorCliente,
        public array $sinUso30,
        public array $sinRoles,
        public array $secretosSinRotar,
    ) {}
}
