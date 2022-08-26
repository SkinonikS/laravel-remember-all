<?php

namespace SkinonikS\Laravel\RememberAll\Concerns;

use RuntimeException;
use Illuminate\Auth\Authenticatable as IlluminateAuthenticatable;

trait Authenticatable
{
    use IlluminateAuthenticatable;

    /**
     * {@inheritDoc}
     */
    public function getRememberToken()
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * {@inheritDoc}
     */
    public function setRememberToken($value)
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * {@inheritDoc}
     */
    public function getRememberTokenName()
    {
        throw new RuntimeException('Not implemented.');
    }
}
