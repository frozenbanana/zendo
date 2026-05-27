<?php

use App\Modules\Hub\Controllers\HubController;
use App\Modules\People\Models\User;
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
