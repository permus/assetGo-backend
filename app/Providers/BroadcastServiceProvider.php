<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load channel authorization routes first
        require base_path('routes/channels.php');
        
        // Note: Broadcasting routes are registered in routes/api.php
        // to ensure they're under the /api prefix with Sanctum auth
    }
}
