<?php

namespace App\Domains\Acceso\Features\VerPersonaConSesion;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/iam/v1/me, for the Angular app: never the token. Adds the CSRF token of the session for
 * the logout form, which is HTTP and not part of the use case.
 */
final class VerPersonaConSesionEndpoint
{
    public function __invoke(VerPersonaConSesionQueryHandler $ver): JsonResponse
    {
        return response()->json(['data' => $ver() + ['csrf' => csrf_token()]]);
    }
}
