<?php

/*
 * Developer access: a login that lives outside the shop's users, used to reset the owner's password
 * when it is forgotten. Set it on the server with: php artisan developer:password
 */
return [
    'username' => env('DEVELOPER_USERNAME'),

    'password_hash' => env('DEVELOPER_PASSWORD_HASH'),

    /** Minutes a developer session stays open without logging in again. */
    'session_minutes' => (int) env('DEVELOPER_SESSION_MINUTES', 30),
];
