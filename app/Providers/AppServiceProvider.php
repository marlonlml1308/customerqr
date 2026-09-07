<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

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
     *
     * Fuerza HTTPS para que el formulario no sea marcado como "no seguro"
     * cuando la app corre detrás de un reverse proxy (Nginx/Apache) en VPS.
     */
    public function boot(): void
    {
        // Confiar en todos los proxies para leer correctamente X-Forwarded-Proto
        $this->app['request']->setTrustedProxies(
            ['REMOTE_ADDR'],
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX
        );

        // Si la variable de entorno indica producción o la URL usa https, forzarlo
        if (config('app.env') === 'production' || str_starts_with(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    }
}
