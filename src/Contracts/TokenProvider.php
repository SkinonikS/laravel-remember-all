<?php

namespace SkinonikS\Laravel\RememberAll\Contracts;

use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

interface TokenProvider
{
    public function retrieveAll(AuthenticatableContract $user);

    public function retrieveByToken(AuthenticatableContract $user, $token): ?Token;

    public function retrieve(AuthenticatableContract $user, $tokenId): ?Token;

    public function invalidateAll(AuthenticatableContract $user);

    public function invalidate(AuthenticatableContract $user, $tokenId);

    public function invalidateByToken(AuthenticatableContract $user, $token);

    public function save(AuthenticatableContract $user, $token): Token;

    public function setSession(SessionContract $session);
}
