<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Web routes working';
});

Route::get('/sentry-test', function () {
    throw new Exception('Sentry test exception');
});