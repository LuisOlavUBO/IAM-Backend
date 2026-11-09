<?php

namespace App\Domains\Clientes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates an OAuth client in oauth_clients with the same attributes API-Auth's
 * Application::createClient sets (Passport's table, without installing Passport). The secret is
 * random, stored as a bcrypt hash, and only returned here, once.
 */
final class NuevoCliente
{
    public const AUTHORIZATION_CODE = 'authorization-code';

    public const CLIENT_CREDENTIALS = 'client-credentials';

    /**
     * @param  list<string>  $redirecciones  Required (at least one) for authorization-code clients
     * @return array{id: string, secreto: string}
     */
    public function crear(int $applicationId, string $nombre, string $tipo, array $redirecciones = []): array
    {
        $grantTypes = match ($tipo) {
            self::AUTHORIZATION_CODE => ['authorization_code', 'refresh_token'],
            self::CLIENT_CREDENTIALS => ['client_credentials'],
            default => throw new InvalidArgumentException("Tipo de cliente no soportado: {$tipo}."),
        };

        if ($tipo === self::AUTHORIZATION_CODE && $redirecciones === []) {
            throw new InvalidArgumentException('Un cliente authorization-code necesita al menos una redirección.');
        }

        // Passport's Client uses HasUuids: a UUID v7.
        $id = Str::lower((string) Str::uuid7());
        $secreto = Str::random(40);

        DB::table('oauth_clients')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'name' => $nombre,
            'secret' => Hash::make($secreto),
            'provider' => $tipo === self::CLIENT_CREDENTIALS ? null : 'users',
            'redirect_uris' => json_encode($tipo === self::AUTHORIZATION_CODE ? array_values($redirecciones) : []),
            'grant_types' => json_encode($grantTypes),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'secreto' => $secreto];
    }
}
