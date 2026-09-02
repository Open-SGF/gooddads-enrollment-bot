<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\DropboxTokenProvider;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Spatie\Dropbox\Client;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(fn (): GenericProvider => new GenericProvider(
            config()->array('services.dropbox.oauth'),
            ['httpClient' => new GuzzleClient(['timeout' => 30])],
        ));

        $this->app->singleton(
            fn (Application $application): Client => new Client($application->make(DropboxTokenProvider::class)),
        );
    }
}
