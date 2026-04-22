<?php

declare(strict_types=1);

use App\Http\Controllers\DropboxAuthController;
use Illuminate\Support\Facades\Route;

$authorizeMiddleware = app()->environment(['local', 'testing']) ? [] : ['auth.basic'];
$callbackMiddleware = app()->environment(['local', 'testing']) ? ['throttle:30,1'] : ['auth.basic', 'throttle:30,1'];

Route::get('/dropbox/authorize', [DropboxAuthController::class, 'authorize'])
    ->middleware($authorizeMiddleware)
    ->name('dropbox.authorize');
Route::get('/dropbox/callback', [DropboxAuthController::class, 'callback'])
    ->middleware($callbackMiddleware)
    ->name('dropbox.callback');