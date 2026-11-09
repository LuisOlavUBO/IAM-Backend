<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary passwords
    |--------------------------------------------------------------------------
    |
    | Hours a temporary password is valid (new users with a password and password resets).
    | API-Auth makes the person change it on the first login and refuses it once expired.
    |
    */

    'clave_temporal_horas' => (int) env('IAM_CLAVE_TEMPORAL_HORAS', 72),

];
