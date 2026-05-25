<?php

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/hub', function () {
    return view('hub', [
        'centers' => \App\Modules\Tenancy\Models\Tenant::where('is_active', true)->get(),
    ]);
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
        'password' => bcrypt(str()->random(32)), // Random since they use Google
    ]);

    Auth::login($user);

    return redirect('/dashboard');
});

