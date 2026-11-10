<?php

use App\Domains\Usuarios\UsuariosServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    UsuariosServiceProvider::class,
];
