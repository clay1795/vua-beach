<?php

return [

    'payment_sandbox_mode' => env('PAYMENT_SANDBOX_MODE', false),
    'payment_sandbox_labels' => env('PAYMENT_SANDBOX_LABELS', ! filter_var(env('RENDER', false), FILTER_VALIDATE_BOOLEAN)),

    'local_tunnel_host_suffixes' => array_values(array_filter(array_map(
        static fn (string $suffix): string => strtolower(trim($suffix)),
        explode(',', (string) env('LOCAL_TUNNEL_HOST_SUFFIXES', '.ngrok-free.dev,.ngrok-free.app')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ghn' => [
        'enabled' => env('GHN_ENABLED', true),
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn/shiip/public-api'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'from_district_id' => env('GHN_FROM_DISTRICT_ID'),
        'webhook_secret' => env('GHN_WEBHOOK_SECRET'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),
        'default_fee' => (int) env('GHN_DEFAULT_FEE', 30000),
    ],

    'momo' => [
        'enabled' => env('MOMO_ENABLED', false),
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
        'return_url' => env('MOMO_RETURN_URL'),
        'ipn_url' => env('MOMO_IPN_URL'),
    ],

    'vnpay' => [
        'enabled' => env('VNPAY_ENABLED', false),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'ipn_url' => env('VNPAY_IPN_URL'),
    ],

    'alerts' => [
        'webhook_url' => env('ALERT_WEBHOOK_URL'),
        // A monitored MAIL_FROM_ADDRESS is a safe default for small installs;
        // larger deployments can route alerts to a dedicated operations inbox.
        'mail_to' => env('ALERT_MAIL_TO') ?: env('MAIL_FROM_ADDRESS'),
    ],

];
