<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/tickets.php';
require __DIR__.'/ticket-settings.php';
