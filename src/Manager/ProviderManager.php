<?php

namespace SkinonikS\Laravel\RememberAll\Manager;

use InvalidArgumentException;
use Illuminate\Foundation\Application;

class ProviderManager
{
    use CreatesUserProviders;
    use CreatesTokenProviders;

    public function __construct(
        protected Application $app
    ) {
    }

    protected function getDefaultProviderName(string $type): ?string
    {
        return $this->app['config']["auth.defaults.providers.$type"];
    }

    protected function getProviderConfiguration(?string $provider, string $type): ?array
    {
        $provider = $provider ?: $this->getDefaultProviderName($type);

        if ($provider) {
            return $this->app['config']["auth.providers.$provider"];
        }

        throw new InvalidArgumentException("No default provider is defined for [$type]");
    }
}
