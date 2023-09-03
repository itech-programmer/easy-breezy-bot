<?php

use App\Http\Controllers\BotManController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::match(['get', 'post'], 'web-hook', [BotManController::class, 'handle']);

