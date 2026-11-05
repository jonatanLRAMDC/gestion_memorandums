<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gerencia propia
    |--------------------------------------------------------------------------
    |
    | Siglas de la gerencia que usa el sistema en la versión 1.
    |
    */

    'siglas_gerencia_propia' => 'GIS',

    /*
    |--------------------------------------------------------------------------
    | Archivos PDF
    |--------------------------------------------------------------------------
    |
    | Disco privado donde se guardan los PDF y tamaño máximo permitido en KB.
    |
    */

    'disco' => 'memorandos',

    'pdf_max_kb' => (int) env('MEMORANDO_PDF_MAX_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Administrador inicial
    |--------------------------------------------------------------------------
    |
    | Datos que usa AdministradorSeeder para crear la primera cuenta.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador del sistema'),
        'email' => env('ADMIN_EMAIL', 'admin@amdc.hn'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
