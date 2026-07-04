<?php

use Illuminate\Support\Facades\Route;

/*
 | Stateless API routes (token/echo). Most of the app's AJAX runs through
 | web.php (session + CSRF) per the plan; this file is reserved for any
 | stateless endpoints and a health ping.
 */

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'data' => ['time' => now()->toIso8601String()],
    'errors' => null,
]));
