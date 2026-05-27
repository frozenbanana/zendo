<?php

use App\Http\Controllers\HealthCheckController;
use App\Modules\Payments\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthCheckController::class)->name('api.v1.health');
    Route::post('/stripe/webhook', StripeWebhookController::class)->name('api.v1.stripe.webhook');
});
