<?php

return [
    /*
    | The edition this installation runs. Editions share the kit and differ by the plugins they require.
    */
    'edition' => [
        'id' => env('LASABRO_EDITION', 'enterprise'),
        'name' => env('LASABRO_EDITION_NAME', 'LASABRO Enterprise'),
    ],

    /*
    | Default theme preset served to frontends (lasabro, cops-classic). The organization setting appearance.preset overrides it.
    */
    'theme' => [
        'preset' => env('LASABRO_THEME_PRESET', 'lasabro'),
    ],

    /*
    | Composer package type that marks a package as a LASABRO plugin (composer.json "type").
    */
    'package_type' => 'lasabro-plugin',

    /*
    | Extra folders whose sub-folders are plugins (each with a composer.json containing extra.lasabro).
    | Useful in a monorepo checkout or while writing a plugin; installed packages are found without it.
    */
    'plugin_paths' => [],

    /*
    | Where the compiled list of active plugins is written. null = bootstrap/cache/lasabro.php
    */
    'cache_path' => null,

    /*
    | How plugin route files are mounted. routes/api.php of a plugin is authenticated and tenant-scoped,
    | routes/public.php is open (declare throttling inside the file).
    */
    'routes' => [
        'prefix' => 'api/v1',
        'public_middleware' => ['api'],
        'auth_middleware' => ['api', 'auth:sanctum', 'org.context'],
    ],
];
