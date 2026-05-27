<?php

use App\Http\Controllers\HealthCheckController;
use App\Modules\Hub\Controllers\HubController;
use App\Modules\Payments\Http\Controllers\StripeWebhookController;
use App\Modules\People\Models\User;
use App\Modules\Registration\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Auth;
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

Route::get('/auth/google', function () {
    return Socialite::driver('google')->redirect();
})->name('auth.google');

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->user();

    $user = User::updateOrCreate([
        'google_id' => $googleUser->id,
    ], [
        'name' => $googleUser->name,
        'email' => $googleUser->email,
        'avatar' => $googleUser->avatar,
        'password' => bcrypt(str()->random(32)),
    ]);

    Auth::login($user);

    return redirect('/dashboard');
});
