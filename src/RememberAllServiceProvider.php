<?php

namespace SkinonikS\Laravel\RememberAll;

use SkinonikS\Laravel\RememberAll\Manager\ProviderManager;
use SkinonikS\Laravel\RememberAll\Guard\SessionGuard;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Illuminate\Auth\AuthManager;

class RememberAllServiceProvider extends IlluminateServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_remember_tokens_table.php' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_remember_tokens_table.php'),
            ], 'migrations');
        }

        $this->app->singleton(ProviderManager::class, static function ($app) {
            return new ProviderManager($app);
        });

        $this->app[AuthManager::class]->extend('remember-all', function ($app, $name, $config) {
            $manager = $app[ProviderManager::class];

            $guard = new SessionGuard(
                $name,
                $manager->createUserProvider($config['providers']['users'] ?? null),
                $manager->createTokenProvider($config['providers']['tokens'] ?? null)
                    ->setSession($sessionStore = $app['session.store']),
                $sessionStore,
            );

            if (isset($config['remember_duration'])) {
                $guard->setRememberDuration($config['remember_duration']);
            }

            return $guard
                ->setCookieJar($app['cookie'])
                ->setDispatcher($app['events'])
                ->setRequest($app->refresh('request', $guard, 'setRequest'));
        });
    }
}
