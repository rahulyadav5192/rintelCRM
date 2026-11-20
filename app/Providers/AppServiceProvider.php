<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Gate::define('view-reports', fn (User $user) => $user->hasPermission(Permission::ViewReports));
        Gate::define('view-activity-logs', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-public-forms', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-settings', fn (User $user) => $user->isSuperAdmin());

        RateLimiter::for('public-leads', function (Request $request) {
            return [
                Limit::perMinute(5)->by('pl:'.$request->ip().':'.$request->route('token')),
                Limit::perHour(30)->by('pl-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
