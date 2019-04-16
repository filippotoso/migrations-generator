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

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This parameter specifies a list of tables for which the generator will 
    | include the soft delete column. You can set it as an array of tables
    | or use '*' (the asterisk string) for all tables.
    |
     */
    'soft_deletes' => '*',

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    |
    | This parameter specifies a list of tables for which the generator will 
    | include the timestamps columns. You can set it as an array of tables
    | or use '*' (the asterisk string) for all tables.
    |
     */
    'timestamps' => '*',

];
