<?php

namespace App\Domains\Acceso;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use UBO\Common\Auth\Exceptions\AuthServerUnavailable;
use UBO\Common\Login\Exceptions\LoginFailed;
use UBO\Common\Login\Exceptions\NoAccess;

/**
 * Errors of the login routes happen in a browser navigation, not in an HttpClient call: they send
 * the person back to the portal with ?error_login=<code> instead of a JSON body (design D3).
 * Other errors (LoginNotConfigured, unexpected ones) are left to ProblemRenderer.
 */
final class ErrorDeLogin
{
    public function __invoke(Throwable $e, Request $request): ?RedirectResponse
    {
        if (! $request->is(trim((string) config('ubo-login.route_prefix'), '/').'/*')) {
            return null;
        }

        $code = match (true) {
            $e instanceof NoAccess => 'SIN_ACCESO',
            $e instanceof LoginFailed => $e->getStatusCode() === 403 ? 'SIN_ACCESO' : 'LOGIN_INVALIDO',
            $e instanceof AuthServerUnavailable => 'IAM_NO_DISPONIBLE',
            default => null,
        };

        if ($code === null) {
            return null;
        }

        $home = (string) config('ubo-login.home', '/');

        return redirect()->to($home.(str_contains($home, '?') ? '&' : '?').'error_login='.$code);
    }
}
