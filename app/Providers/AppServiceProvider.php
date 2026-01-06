<?php

namespace App\Providers;

use App\Models\Service;
use App\Policies\ServicePolicy;
use Illuminate\Support\Facades\Gate;
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
        // Register policies
        Gate::policy(Service::class, ServicePolicy::class);

        // Register observers
        \App\Models\Appointment::observe(\App\Observers\AppointmentObserver::class);
    }
}
