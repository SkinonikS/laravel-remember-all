<?php

namespace SkinonikS\Laravel\RememberAll\Contracts;

interface Token
{
    public function getIdentifier();

    public function getIdentifierName(): string;

    public function getToken();

    public function getTokenName(): string;

    public function getUserIdentifier();

    public function getUserIdentifierName(): string;

    public function getSessionIdentifier();

    public function getSessionIdentifierName(): string;
}
