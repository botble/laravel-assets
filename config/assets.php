<?php

return [
    'offline'        => env('ASSETS_OFFLINE', true),
    'enable_version' => env('ASSETS_ENABLE_VERSION', false),
    'version'        => env('ASSETS_VERSION', time()),
    'scripts'        => [
        'modernizr',
        'app',
    ],
    'styles'         => [
        'bootstrap',
    ],
    'resources'      => [
        'scripts' => [
            'app'       => [
                'use_cdn'  => false,
                'location' => 'footer',
                'src'      => [
                    'local' => '/js/app.js',
                ],
            ],
            'modernizr' => [
                'use_cdn'  => true,
                'location' => 'header',
                'src'      => [
                    'local' => '/vendor/core/packages/modernizr/modernizr.min.js',
                    'cdn'   => '//cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.js',
                ],
            ],
        ],
        'styles'  => [
            'bootstrap' => [
                'use_cdn'    => true,
                'location'   => 'header',
                'src'        => [
                    'local' => '/packages/bootstrap/css/bootstrap.min.css',
                    'cdn'   => '//stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css',
                ],
                'attributes' => [
                    'integrity'   => 'sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB',
                    'crossorigin' => 'anonymous',
                ],
            ],
        ],
    ],
];
