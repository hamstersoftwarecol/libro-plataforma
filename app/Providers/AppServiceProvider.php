<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Settings\RuntimeConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.modern');

        app(RuntimeConfig::class)->apply();

        Gate::define('access-admin', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Editor));
        Gate::define('manage-books', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Editor));
        Gate::define('view-finances', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-subscribers', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-settings', fn (User $user): bool => $user->isAdmin());

        RateLimiter::for('otp', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('reader', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
