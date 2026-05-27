<?php

use App\Http\Controllers\HealthCheckController;
use App\Modules\Hub\Controllers\HubController;
use App\Modules\Payments\Http\Controllers\StripeWebhookController;
use App\Modules\Registration\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('hub')->name('hub.')->group(function () {
    Route::get('/', [HubController::class, 'home'])->name('home');
    Route::get('/centers', [HubController::class, 'centers'])->name('centers');
    Route::get('/events', [HubController::class, 'events'])->name('events');
    Route::get('/events/{id}', [HubController::class, 'eventDetail'])->name('events.show');
    Route::get('/teachers', [HubController::class, 'teachers'])->name('teachers');
});

Route::prefix('registrations')->name('registrations.')->group(function () {
    Route::get('/create', [RegistrationController::class, 'create'])->name('create');
    Route::post('/', [RegistrationController::class, 'store'])->name('store');
    Route::get('/{id}', [RegistrationController::class, 'show'])->name('show');
});

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/health', HealthCheckController::class)->name('health');

Route::middleware(['auth'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
