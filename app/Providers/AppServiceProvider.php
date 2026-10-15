<?php

namespace App\Providers;

use App\Actions\AppSettings\AppBranding;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AppBranding::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureBranding();
    }

    /**
     * Use the App settings for the app name and email sender, and read them again before every queued
     * job so a long-running worker picks up changes.
     */
    protected function configureBranding(): void
    {
        $this->app->make(AppBranding::class)->applyToConfig();

        Queue::before(function () {
            $branding = $this->app->make(AppBranding::class);
            $branding->refresh();
            $branding->applyToConfig();
        });
    }

    /**
     * Register policies that cannot be auto-discovered.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
