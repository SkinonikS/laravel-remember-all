<?php

namespace SkinonikS\Laravel\RememberAll\UserProviders;

use RuntimeException;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\EloquentUserProvider as IlluminateEloquentUserProvider;

class EloquentUserProvider extends IlluminateEloquentUserProvider
{
    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(AuthenticatableContract $user, $token)
    {
        throw new RuntimeException('Not implemented.');
    }
}
