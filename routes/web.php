<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'setup']);
Route::get('/experience', [PageController::class, 'experience']);
Route::get('/dashboard', [PageController::class, 'dashboard']);
