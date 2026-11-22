<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server, so that they don't collide.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration (Isolated Carrier Queues)
    |--------------------------------------------------------------------------
    |
    | 4 Isolated Queues:
    | - critical: CoA disconnects, payment unblock <3s, NOC kicks
    | - notifications: SMS, WhatsApp, Email, OTP
    | - network: RouterOS API sync, SNMP/ICMP polling, OLT/ONT discovery
    | - default: dunning buckets, monthly invoices, CGNAT ClickHouse sync
    |
    */

    'environments' => [
        'production' => [
            'supervisor-critical' => [
                'connection' => 'redis',
                'queue' => ['critical'],
                'balance' => 'simple',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 10,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 5,
                'nice' => 0,
            ],
            'supervisor-notifications' => [
                'connection' => 'redis',
                'queue' => ['notifications'],
                'balance' => 'auto',
                'maxProcesses' => 5,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 30,
                'nice' => 0,
            ],
            'supervisor-network' => [
                'connection' => 'redis',
                'queue' => ['network'],
                'balance' => 'auto',
                'maxProcesses' => 5,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 30,
                'nice' => 0,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'maxProcesses' => 5,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-critical' => [
                'connection' => 'redis',
                'queue' => ['critical'],
                'balance' => 'simple',
                'maxProcesses' => 3,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 5,
            ],
            'supervisor-notifications' => [
                'connection' => 'redis',
                'queue' => ['notifications'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 30,
            ],
            'supervisor-network' => [
                'connection' => 'redis',
                'queue' => ['network'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 30,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
            ],
        ],
    ],
];
