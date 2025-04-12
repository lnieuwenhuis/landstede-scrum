<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            \SocialiteProviders\Azure\AzureExtendSocialite::class.'@handle',
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}