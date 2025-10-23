<?php

use Illuminate\Support\Facades\Route;

use Gzai\LaravelBoxAdapter\Http\Controllers\BoxAuthController;
use Gzai\LaravelBoxAdapter\Http\Controllers\BoxUserController;

Route::prefix('box')->name('box.')->group(function () {

    Route::get('login', [BoxAuthController::class, 'login'])
        ->name('login');

    Route::get('callback', [BoxAuthController::class, 'callback'])
        ->name('callback');

    Route::get('me', [BoxUserController::class, 'me'])
        ->name('me');

});
