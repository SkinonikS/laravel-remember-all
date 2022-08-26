<?php

namespace SkinonikS\Laravel\RememberAll\Manager;

use SkinonikS\Laravel\RememberAll\UserProviders\EloquentUserProvider;
use InvalidArgumentException;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Closure;

trait CreatesUserProviders
{
    protected array $customUserProviderResolvers = [];

    public function registerUsersProvider(string $name, Closure $resolver): self
    {
        $this->customTokenProviderResolvers[$name] = $resolver;

        return $this;
    }

    public function createUserProvider(?string $provider): UserProviderContract
    {
        $config = $this->getProviderConfiguration($provider, 'users');

        if (!$config) {
            throw new InvalidArgumentException("Config is not defined for user provider [$provider].");
        }

        if (isset($this->customUserProviderResolvers[$driver = $config['driver']])) {
            return call_user_func(
                $this->customUserProviderResolvers[$driver],
                $this->app,
                $config
            );
        }

        return match ($driver) {
            'eloquent' => $this->createUserEloquentProvider($config),
            default => throw new InvalidArgumentException("Authentication user provider [$driver] is not supported."),
        };
    }

    protected function createUserEloquentProvider(array $config): EloquentUserProvider
    {
        return new EloquentUserProvider($this->app['hash'], $config['model']);
    }

    abstract protected function getProviderConfiguration(string $provider, string $type): ?array;
}
