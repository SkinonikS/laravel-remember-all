<?php

namespace SkinonikS\Laravel\RememberAll\TokenProviders;

use SkinonikS\Laravel\RememberAll\Contracts\TokenProvider;
use SkinonikS\Laravel\RememberAll\Contracts\Token;
use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class EloquentTokenProvider implements TokenProvider
{
    protected ?SessionContract $session = null;

    public function __construct(
        protected string $model,
    ) {
    }

    public function retrieveAll(AuthenticatableContract $user)
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->get()
            ->all();
    }

    public function retrieve(AuthenticatableContract $user, $tokenId): ?Token
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getIdentifierName(), $tokenId)
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->first();
    }

    public function retrieveByToken(AuthenticatableContract $user, $tokenId): ?Token
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getTokenName(), $tokenId)
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->first();
    }

    public function invalidateAll(AuthenticatableContract $user)
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->delete();
    }

    public function invalidate(AuthenticatableContract $user, $tokenId)
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getIdentifierName(), $tokenId)
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->delete() === 1;
    }

    public function invalidateByToken(AuthenticatableContract $user, $token)
    {
        $model = $this->newModel();

        return $model->newQuery()
            ->where($model->getTokenName(), $token)
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier())
            ->delete() === 1;
    }

    public function save(AuthenticatableContract $user, $token): Token
    {
        $model = $this->newModel();

        /**
         * @var \Illuminate\Database\Eloquent\Model&\SkinonikS\Laravel\RememberAll\Contracts\Token
         */
        $token = $model->newQuery()
            ->forceCreate([
                $model->getTokenName() => $token,
                $model->getUserIdentifierName() => $user->getAuthIdentifier(),
                $model->getSessionIdentifierName() => $this->getSession()->getId(),
            ]);

        return $token;
    }

    public function setSession(SessionContract $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function getSession(): SessionContract
    {
        if (!$this->session) {
            throw new RuntimeException('Session is not provided.');
        }

        return $this->session;
    }

    protected function newModel(): Model
    {
        return new $this->model;
    }
}
