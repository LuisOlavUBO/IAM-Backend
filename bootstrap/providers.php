<?php

use App\Domains\Aplicaciones\AplicacionesServiceProvider;
use App\Domains\Auditoria\AuditoriaServiceProvider;
use App\Domains\Clientes\ClientesServiceProvider;
use App\Domains\Indicadores\IndicadoresServiceProvider;
use App\Domains\Usuarios\UsuariosServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AplicacionesServiceProvider::class,
    AuditoriaServiceProvider::class,
    ClientesServiceProvider::class,
    IndicadoresServiceProvider::class,
    UsuariosServiceProvider::class,
];
