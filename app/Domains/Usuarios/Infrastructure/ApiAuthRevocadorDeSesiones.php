<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Domain\Interfaces\IRevocadorDeSesiones;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UBO\Common\Auth\Contracts\ServiceTokenProvider;

/**
 * IRevocadorDeSesiones against API-Auth: POST /api/auth/v1/users/{uuid}/revoke with the token of the
 * iam-admin-sistema client (scope sessions:revoke), obtained and cached by ubo/common (design D5).
 */
final class ApiAuthRevocadorDeSesiones implements IRevocadorDeSesiones
{
    private const SCOPES = ['sessions:revoke'];

    public function __construct(private readonly ServiceTokenProvider $tokens) {}

    public function revocar(string $uuid): bool
    {
        try {
            $respuesta = Http::withToken($this->tokens->token(self::SCOPES))
                ->acceptJson()
                ->timeout(5)
                ->post(rtrim((string) config('ubo-auth.url'), '/')."/api/auth/v1/users/{$uuid}/revoke");
        } catch (Throwable $e) {
            Log::warning('No se pudieron revocar las sesiones en API-Auth.', ['uuid' => $uuid, 'error' => $e::class]);

            return false;
        }

        if ($respuesta->status() === 401) {
            // The cached token was refused (rotated secret, revoked client): the next attempt asks for a new one.
            $this->tokens->forget(self::SCOPES);
        }

        // 404: the person does not exist in API-Auth, so there is nothing to close.
        if ($respuesta->status() === 204 || $respuesta->status() === 404) {
            return true;
        }

        Log::warning('API-Auth no confirmó la revocación de sesiones.', ['uuid' => $uuid, 'status' => $respuesta->status()]);

        return false;
    }
}
