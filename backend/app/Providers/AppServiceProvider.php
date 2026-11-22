<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Billing\DunningStateMachineInterface;
use App\Contracts\Billing\ProrataCalculatorInterface;
use App\Contracts\Notifications\NotificationDispatcherInterface;
use App\Contracts\Payments\WebhookIdempotencyInterface;
use App\Services\Billing\DunningStateMachine;
use App\Services\Billing\ProrataCalculator;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Payments\WebhookIdempotencyService;
use App\Services\Rbac\RbacService;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->singleton(RbacService::class, function () {
            return new RbacService;
        });

        // Group A Service Interface Bindings
        $this->app->bind(NotificationDispatcherInterface::class, NotificationDispatcher::class);
        $this->app->bind(DunningStateMachineInterface::class, DunningStateMachine::class);
        $this->app->bind(ProrataCalculatorInterface::class, ProrataCalculator::class);
        $this->app->bind(WebhookIdempotencyInterface::class, WebhookIdempotencyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
