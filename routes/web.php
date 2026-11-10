<?php

use Illuminate\Support\Facades\Route;

// The portal (BFF for the Angular app on the same origin): it needs the session and CSRF of the "web"
// group, under the same /api/iam/v1 prefix as the API. The login routes (api/iam/v1/auth/login,
// callback and logout) are registered by the Login module of UBO.Common (UBO_LOGIN_ROUTE_PREFIX).
// Without a session these routes answer 401 in RFC 9457, never a redirect.

Route::prefix('api/iam/v1')->middleware('ubo.login')->group(function () {
    // One route file per domain: app/Domains/<Dominio>/routes/v1.php
    foreach (['Acceso', 'Aplicaciones', 'Usuarios', 'Clientes', 'Auditoria'] as $domain) {
        require app_path("Domains/{$domain}/routes/v1.php");
    }
});
