<?php
declare(strict_types=1);

// XAMPP: MySQL lietotājs parasti ir root bez paroles.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'noma',
        'username' => 'root',
        'password' => '',
    ],
    'auth' => [
        'session_name' => 'noma_session',
        'idle_seconds' => 7200,
        'absolute_seconds' => 43200,
        'remember_days' => 30,
        'window_seconds' => 900,
        'account_attempts' => 5,
        'ip_attempts' => 30,
        'registration_attempts' => 10,
    ],
];
