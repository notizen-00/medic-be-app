<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mitra/login', function () {
    $appUrl = config('app.url');
    $defaultScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: (request()->isSecure() ? 'https' : 'http');
    $defaultHost = parse_url($appUrl, PHP_URL_HOST) ?: request()->getHost();

    return view('mitra.login', [
        'reverbKey' => env('REVERB_APP_KEY', 'medic-app-key'),
        'reverbHost' => env('VITE_REVERB_HOST', $defaultHost),
        'reverbPort' => env('VITE_REVERB_PORT', $defaultScheme === 'https' ? '443' : '8080'),
        'reverbScheme' => env('VITE_REVERB_SCHEME', $defaultScheme),
    ]);
});
