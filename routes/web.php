<?php

declare(strict_types=1);

use App\Http\Controllers\DropboxAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/dropbox/authorize', [DropboxAuthController::class, 'authorize'])
    ->middleware('dropbox.basic')
    ->name('dropbox.authorize');

Route::get('/dropbox/callback', [DropboxAuthController::class, 'callback'])
    ->middleware(['dropbox.basic', 'throttle:30,1'])
    ->name('dropbox.callback');
