<?php

namespace SkinonikS\Laravel\RememberAll\Manager;

use SkinonikS\Laravel\RememberAll\TokenProviders\EloquentTokenProvider;
use SkinonikS\Laravel\RememberAll\Contracts\TokenProvider as TokenProviderContract;
use InvalidArgumentException;
use Closure;

trait CreatesTokenProviders
{
    protected array $customTokenProviderResolvers = [];

    public function registerTokenProvider(string $name, Closure $resolver): self
    {
        $this->customTokenProviderResolvers[$name] = $resolver;

        return $this;
    }

    public function createTokenProvider(?string $provider): TokenProviderContract
    {
        $config = $this->getProviderConfiguration($provider, 'tokens');

        if (!$config) {
            throw new InvalidArgumentException("Config is not defined for user provider [$provider].");
        }

        if (isset($this->customTokenProviderResolvers[$driver = $config['driver']])) {
            return call_user_func(
                $this->customTokenProviderResolvers[$driver],
                $this->app,
                $config
            );
        }

        return match ($driver) {
            'eloquent' => $this->createTokenEloquentProvider($config),
            default => throw new InvalidArgumentException("Authentication token provider [$driver] is not supported."),
        };
    }

    protected function createTokenEloquentProvider(array $config): EloquentTokenProvider
    {
        return new EloquentTokenProvider($config['model']);
    }

    abstract protected function getProviderConfiguration(string $provider, string $type): ?array;
}
