<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MlPredictionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MlPredictionService::class, fn () => MlPredictionService::fromConfig());
    }

    public function boot(): void
    {
        Model::unguard(false);

        // Di production seluruh URL dipaksa HTTPS.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
