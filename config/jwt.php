<?php

/*
|--------------------------------------------------------------------------
| JWT Authentication Configuration
|--------------------------------------------------------------------------
|
| This is the configuration for tymon/jwt-auth package
| 
| Token Duration:
| - Access Token (TTL): 18 hours (1080 minutes)
| - Refresh Token: 30 days (43200 minutes)
|
| This covers full operational hours (07:00 - 23:00 = 16 hours + 2h buffer)
*/

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Secret
    |--------------------------------------------------------------------------
    |
    | Don't forget to set this in your .env file
    | Run: php artisan jwt:secret
    |
    */

    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Keys
    |--------------------------------------------------------------------------
    |
    | The algorithm you are using, and the keys required for RS256/RS384/RS512
    | algorithms. For HMAC algorithms (HS256, HS384, HS512), the 'secret' key
    | will be used automatically.
    |
    */

    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT time to live (TTL)
    |--------------------------------------------------------------------------
    |
    | Specify the length of time (in minutes) that the token will be valid for.
    | 
    | Default: 1080 minutes (18 hours)
    | Covers: 07:00 - 23:00 operational hours + 2 hour buffer
    |
    */

    'ttl' => env('JWT_TTL', 1080),

    /*
    |--------------------------------------------------------------------------
    | Refresh time to live
    |--------------------------------------------------------------------------
    |
    | Specify the length of time (in minutes) that the token can be refreshed
    | within. I.E. The user can refresh their token within a 30 day window of
    | the original token being created until they must re-authenticate.
    |
    | Default: 43200 minutes (30 days)
    |
    */

    'refresh_ttl' => env('JWT_REFRESH_TTL', 43200),

    /*
    |--------------------------------------------------------------------------
    | JWT hashing algorithm
    |--------------------------------------------------------------------------
    |
    | Specify the hashing algorithm that will be used to sign the token.
    | HS256 is recommended for internal systems.
    |
    */

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Required Claims
    |--------------------------------------------------------------------------
    |
    | Specify the required claims that must exist in any token.
    | A TokenInvalidException will be thrown if any of these are missing.
    |
    */

    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistent Claims
    |--------------------------------------------------------------------------
    |
    | Specify the claim keys to be persisted when refreshing a token.
    | `sub` and `iat` will automatically be persisted, in
    | addition to the these claims.
    |
    */

    'persistent_claims' => [
        // 'foo',
        // 'bar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lock Subject
    |--------------------------------------------------------------------------
    |
    | This will determine whether a `prv` claim is automatically added to
    | the token. The purpose of this is to ensure that if you have multiple
    | authentication models e.g. `App\User` & `App\OtherPerson`, then we
    | should prevent one authentication request from impersonating another,
    | if 2 tokens happen to have the same id across the 2 different models.
    |
    */

    'lock_subject' => true,

    /*
    |--------------------------------------------------------------------------
    | Leeway
    |--------------------------------------------------------------------------
    |
    | This property gives the jwt timestamp claims some "leeway".
    | Meaning that if you have any unavoidable slight clock skew on
    | any of your servers then this will afford you some level of cushioning.
    |
    */

    'leeway' => env('JWT_LEEWAY', 0),

    /*
    |--------------------------------------------------------------------------
    | Blacklist Enabled
    |--------------------------------------------------------------------------
    |
    | In order to invalidate tokens, you must have the blacklist enabled.
    | If you do not want or need this functionality, then set this to false.
    |
    | For this system, blacklist is DISABLED for simplicity and performance.
    | Tokens will expire naturally after TTL (18 hours).
    |
    */

    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Blacklist Grace Period
    |--------------------------------------------------------------------------
    |
    | When multiple concurrent requests are made with the same JWT,
    | it is possible that some of them fail, due to token regeneration
    | on every request. Set grace period in seconds to prevent parallel
    | request failure.
    |
    */

    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    /*
    |--------------------------------------------------------------------------
    | Show blacklisted token option
    |--------------------------------------------------------------------------
    |
    | Specify if you want to show blacklisted token exception on the response.
    |
    */

    'show_black_list_exception' => env('JWT_SHOW_BLACKLIST_EXCEPTION', true),

    /*
    |--------------------------------------------------------------------------
    | Cookies encryption
    |--------------------------------------------------------------------------
    |
    | By default Laravel encrypts cookies for security reasons.
    | If you decide to not decrypt cookies, you will have to configure Laravel
    | to not encrypt your cookie token by adding its name to the $except
    | array available in the middleware "EncryptCookies" provided by Laravel.
    | see https://laravel.com/docs/master/responses#cookies-and-encryption
    | for details.
    |
    */

    'decrypt_cookies' => false,

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Specify the various providers used throughout the package.
    |
    */

    'providers' => [

        /*
        |--------------------------------------------------------------------------
        | JWT Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to create and decode the tokens.
        |
        */

        'jwt' => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,

        /*
        |--------------------------------------------------------------------------
        | Authentication Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to authenticate users.
        |
        */

        'auth' => Tymon\JWTAuth\Providers\Auth\Illuminate::class,

        /*
        |--------------------------------------------------------------------------
        | Storage Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to store tokens in the blacklist.
        |
        */

        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,

    ],

];