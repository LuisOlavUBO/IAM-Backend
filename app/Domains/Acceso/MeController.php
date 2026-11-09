<?php

namespace App\Domains\Acceso;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * The person with a session, for the Angular app: who they are and what they may do. Never the
 * token. The name and email come from users, which API-IAM owns.
 */
final class MeController
{
    public function __invoke(): JsonResponse
    {
        /** @var UboUser $person */
        $person = Auth::guard('ubo')->user();
        $uuid = Str::lower($person->getAuthIdentifier());

        $user = DB::table('users')->where('uuid', $uuid)->first(['name', 'email']);

        return response()->json(['data' => [
            'uuid' => $uuid,
            'nombre' => $user?->name,
            'correo' => $user?->email,
            'roles' => array_values($person->roles),
            'permisos' => array_values($person->scopes),
            'csrf' => csrf_token(),
        ]]);
    }
}
