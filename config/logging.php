<?php

use App\Logging\RedactSensitiveData;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'daily' => [
            'driver' => 'daily',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'payment' => [
            'driver' => 'daily',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/payment.log'),
            'level' => env('PAYMENT_LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'ghn' => [
            'driver' => 'daily',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/ghn.log'),
            'level' => env('GHN_LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'mail' => [
            'driver' => 'daily',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/mail.log'),
            'level' => env('MAIL_LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'inventory' => [
            'driver' => 'daily',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/inventory.log'),
            'level' => env('INVENTORY_LOG_LEVEL', 'info'),
            'days' => 90,
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'monthly' => [
            'driver' => 'monthly',
            'tap' => [RedactSensitiveData::class],
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
            'permission' => 0640,
        ],

        'slack' => [
            'driver' => 'slack',
            'tap' => [RedactSensitiveData::class],
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'tap' => [RedactSensitiveData::class],
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'tap' => [RedactSensitiveData::class],
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'tap' => [RedactSensitiveData::class],
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'tap' => [RedactSensitiveData::class],
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
