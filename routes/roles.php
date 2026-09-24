<?php

use App\Livewire\Roles\RoleForm;
use App\Livewire\Roles\RoleList;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::middleware(['auth', 'verified'])->prefix('roles')->name('roles.')->group(function () {
    Route::livewire('/', RoleList::class)
        ->middleware('can:viewAny,'.Role::class)
        ->name('index');

    Route::livewire('create', RoleForm::class)
        ->middleware('can:create,'.Role::class)
        ->name('create');

    Route::livewire('{role}/edit', RoleForm::class)
        ->middleware('can:update,role')
        ->name('edit');
});
