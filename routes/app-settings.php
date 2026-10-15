<?php

use App\Livewire\AppSettings\AppSettingsForm;
use App\Models\AppSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('app-settings', AppSettingsForm::class)
        ->middleware('can:view,'.AppSettings::class)
        ->name('app-settings');
});
