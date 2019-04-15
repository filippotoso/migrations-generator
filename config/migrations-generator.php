<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | This array specifies a list of tables for which the generator will not
    | create migrations. This is usually used to avoid the creation of Laravel
    | own migrations like jobs, queue, and so on.
    |
 */

    'exclude' => [

        // Laravel
        'cache', 'failed_jobs', 'jobs', 'migrations', 'password_resets', 'sessions', 'users',

        // Laravel Passport package
        // 'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients', 'oauth_personal_access_clients', 'oauth_refresh_tokens',

        // Laratrust package
        // 'roles', 'permissions', 'teams', 'role_user', 'permission_role', 'permission_user',

    ],

];
