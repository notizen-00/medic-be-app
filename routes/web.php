<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mitra/login', function () {
    return view('mitra.login', [
        'reverbKey' => env('REVERB_APP_KEY', 'medic-app-key'),
        'reverbHost' => env('VITE_REVERB_HOST', request()->getHost()),
        'reverbPort' => env('VITE_REVERB_PORT', '8080'),
        'reverbScheme' => env('VITE_REVERB_SCHEME', request()->isSecure() ? 'https' : 'http'),
    ]);
});
