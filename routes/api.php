<?php

use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PlanewardWebhookController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Every route here except the webhook authenticates with the viewer token.
// Scope data by the verified viewer only: Viewer::from($request).

Route::middleware('viewer')->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
});

Route::middleware('viewer:admin')->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/members', MemberController::class);
    Route::post('/announce', AnnounceController::class);
});

Route::post('/webhooks/planeward', PlanewardWebhookController::class);
