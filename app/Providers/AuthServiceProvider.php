<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */

    public function register()
    {
        parent::register();
        $this->app->instance('abilities', include base_path('rules/rules.php'));
    }


    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {

        // Auth::shouldUse('admin');
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user->super_admin) {
                return true;
            }
        });

        // Abilities live grouped by section in rules/rules.php; flatten
        // the groups so every ability gets a Gate. A flat (non-array)
        // entry is still accepted, so old-style entries keep working.
        foreach ($this->app->make('abilities') as $code => $label) {
            if (is_array($label)) {
                foreach ($label as $ability => $abilityLabel) {
                    Gate::define($ability, function ($admin) use ($ability) {
                        return $admin->hasAbility($ability);
                    });
                }
                continue;
            }

            Gate::define($code, function ($admin) use ($code) {
                return $admin->hasAbility($code);
            });
        }
    }
}
