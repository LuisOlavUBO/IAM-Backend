<?php

namespace App\Domains\Clientes\Domain;

use App\Domains\Clientes\Domain\Dto\NuevoClienteDto;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds a confidential OAuth client with the same attributes API-Auth's Application::createClient sets
 * (Passport's table, without installing Passport), without saving it: IClientesRepository::crear does
 * (design D2 of clientes-crear). The secret is random and only its bcrypt hash is stored.
 */
final class NuevoCliente
{
    public const AUTHORIZATION_CODE = 'authorization-code';

    public const CLIENT_CREDENTIALS = 'client-credentials';

    public const TIPOS = [self::AUTHORIZATION_CODE, self::CLIENT_CREDENTIALS];

    /**
     * @param  list<string>  $redirecciones  Required (at least one) for authorization-code clients; ignored for client-credentials
     */
    public static function armar(string $nombre, string $tipo, array $redirecciones = []): NuevoClienteDto
    {
        $concesiones = match ($tipo) {
            self::AUTHORIZATION_CODE => ['authorization_code', 'refresh_token'],
            self::CLIENT_CREDENTIALS => ['client_credentials'],
            default => throw new InvalidArgumentException("Tipo de cliente no soportado: {$tipo}."),
        };

        if ($tipo === self::AUTHORIZATION_CODE && $redirecciones === []) {
            throw new InvalidArgumentException('Un cliente authorization-code necesita al menos una redirección.');
        }

        $secreto = SecretoDeCliente::generar();

        return new NuevoClienteDto(
            // Passport's Client uses HasUuids: a UUID v7.
            id: Str::lower((string) Str::uuid7()),
            nombre: $nombre,
            tipo: $tipo,
            redirecciones: $tipo === self::AUTHORIZATION_CODE ? array_values($redirecciones) : [],
            concesiones: $concesiones,
            provider: $tipo === self::CLIENT_CREDENTIALS ? null : 'users',
            hashSecreto: $secreto->hash(),
            secreto: $secreto->secreto,
        );
    }
}
