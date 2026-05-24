<?php

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
