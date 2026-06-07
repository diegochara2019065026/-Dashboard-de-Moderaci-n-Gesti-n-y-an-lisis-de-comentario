<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas que aceptan peticiones CORS
    |--------------------------------------------------------------------------
    | 'api/*' permite que cualquier endpoint /api/... sea llamado desde
    | otros dominios (tu página web externa).
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Orígenes permitidos
    |--------------------------------------------------------------------------
    | En desarrollo se acepta cualquier origen ('*').
    | En producción reemplaza '*' con tu dominio real, por ejemplo:
    |   'allowed_origins' => ['https://miforo.com'],
    */

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Necesario si el frontend envía cookies o cabeceras Authorization.
    | Cambiar a true solo si usas autenticación con sesión/token.
    */
    'supports_credentials' => false,

];
