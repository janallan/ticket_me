<?php

use App\Livewire\Users\UserForm;
use App\Livewire\Users\UserList;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('users')->name('users.')->group(function () {
    Route::livewire('/', UserList::class)
        ->middleware('can:viewAny,'.User::class)
        ->name('index');

    Route::livewire('create', UserForm::class)
        ->middleware('can:create,'.User::class)
        ->name('create');

    Route::livewire('{user}/edit', UserForm::class)
        ->middleware('can:update,user')
        ->name('edit');
});
