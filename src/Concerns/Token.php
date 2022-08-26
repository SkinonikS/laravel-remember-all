<?php

namespace SkinonikS\Laravel\RememberAll\Concerns;

trait Token
{
    public function getIdentifier(): string|int
    {
        return $this->{$this->getIdentifierName()};
    }

    public function getIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getToken(): string|int
    {
        return $this->{$this->getTokenName()};
    }

    public function getTokenName(): string
    {
        return 'token';
    }

    public function getUserIdentifier(): string|int
    {
        return $this->{$this->getUserIdentifierName()};
    }

    public function getUserIdentifierName(): string
    {
        return 'user_id';
    }

    public function getSessionIdentifier(): string
    {
        return $this->{$this->getSessionIdentifierName()};
    }

    public function getSessionIdentifierName(): string
    {
        return 'session_id';
    }
}
