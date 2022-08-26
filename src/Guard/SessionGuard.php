<?php

namespace SkinonikS\Laravel\RememberAll\Guard;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use SkinonikS\Laravel\RememberAll\Contracts\TokenProvider as TokenProviderContract;
use RuntimeException;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Cookie\QueueingFactory as QueueingFactoryContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Illuminate\Contracts\Auth\SupportsBasicAuth as SupportsBasicAuthContract;
use Illuminate\Contracts\Auth\StatefulGuard as StatefulGuardContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Attempting;

class SessionGuard implements StatefulGuardContract, SupportsBasicAuthContract
{
    use GuardHelpers, Macroable;

    protected ?AuthenticatableContract $lastAttempted = null;

    protected bool $viaRemember = false;

    protected int $rememberDuration = 2628000;

    protected ?QueueingFactoryContract $cookie = null;

    protected ?DispatcherContract $events = null;

    protected bool $loggedOut = false;

    protected bool $recallAttempted = false;

    public function __construct(
        public readonly string $name,
        protected UserProviderContract $userProvider,
        protected TokenProviderContract $tokenProvider,
        protected SessionContract $session,
        protected ?Request $request = null
    ) {
    }

    public function listOfActiveSession()
    {
        if (!($user = $this->user())) {
            return [];
        }

        return $this->tokenProvider->retrieveAll($user);
    }

    public function logoutOtherDevice($tokenId)
    {
        if (!($user = $this->user())) {
            return false;
        }

        if ($token = $this->tokenProvider->retrieve($user, $tokenId)) {
            $this->tokenProvider->invalidate($user, $tokenId);

            $this->session->getHandler()->destroy($token['session_id']);

            $this->fireOtherDeviceLogoutEvent($user);

            return true;
        }

        return false;
    }
    /**
     * {@inheritDoc}
     */
    public function user()
    {
        if ($this->loggedOut) {
            return;
        }

        if ($this->user) {
            return $this->user;
        }

        if ($id = $this->session->get($this->getName())) {
            if ($user = $this->userProvider->retrieveById($id)) {
                $this->fireAuthenticatedEvent($user);

                return $this->user = $user;
            }

            return;
        }

        if ($recaller = $this->getRecaller()) {
            $user = $this->tryToGetUserFromRecaller($recaller);

            if ($user) {
                $this->updateSession($this->user->getAuthIdentifier());

                $this->fireLoginEvent($this->user, true);

                return $this->user = $user;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function loginUsingId($id, $remember = false)
    {
        if (($user = $this->provider->retrieveById($id)) !== null) {
            $this->login($user, $remember);

            return $user;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function login(AuthenticatableContract $user, $remember = false)
    {
        if (($currentUser = $this->user()) && ($recaller = $this->recaller())) {
            $this->tokenProvider->invalidateByToken($currentUser, $recaller->token());
        }

        $this->updateSession($user->getAuthIdentifier());

        $this->tokenProvider->save($user, $token = $this->createNewToken());

        $this->queueRecallerCookie($user, $token);

        $this->fireLoginEvent($user, $remember = true);

        $this->setUser($user);
    }

    /**
     * {@inheritDoc}
     */
    public function logout()
    {
        $user = $this->user();

        $this->clearUserDataFromStorage($recaller = $this->recaller());

        if ($user && $recaller) {
            $this->tokenProvider->invalidateByToken($user, $recaller->token());
        }

        $this->fireCurrentDeviceLogoutEvent($user);
        $this->fireLogoutEvent($user);

        $this->user = null;
        $this->loggedOut = true;
    }

    /**
     * {@inheritDoc}
     */
    public function id()
    {
        if ($this->loggedOut) {
            return null;
        }

        return $this->user()
            ? $this->user()->getAuthIdentifier()
            : $this->session->get($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function once(array $credentials = [])
    {
        $this->fireAttemptEvent($credentials);

        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function onceUsingId($id)
    {
        if ($user = $this->provider->retrieveById($id)) {
            $this->setUser($user);

            return $user;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = [])
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        return $this->hasValidCredentials($user, $credentials);
    }

    /**
     * {@inheritDoc}
     */
    public function basic($field = 'email', $extraConditions = [])
    {
        if ($this->check()) {
            return;
        }

        if ($this->attemptBasic($this->getRequest(), $field, $extraConditions)) {
            return;
        }

        return $this->failedBasicResponse();
    }

    /**
     * {@inheritDoc}
     */
    public function onceBasic($field = 'email', $extraConditions = [])
    {
        $credentials = $this->basicCredentials($this->getRequest(), $field);

        if (!$this->once(array_merge($credentials, $extraConditions))) {
            return $this->failedBasicResponse();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        $this->fireAttemptEvent($credentials, $remember);

        $this->lastAttempted = $user = $this->userProvider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user, $remember);

            return true;
        }

        $this->fireFailedEvent($user, $credentials);

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function attemptWhen(array $credentials = [], $callbacks = null, $remember = false)
    {
        $this->fireAttemptEvent($credentials, $remember);

        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials) && $this->shouldLogin($callbacks, $user)) {
            $this->login($user, $remember);

            return true;
        }

        $this->fireFailedEvent($user, $credentials);

        return false;
    }

    public function attempting($callback): void
    {
        $this->events?->listen(Events\Attempting::class, $callback);
    }

    public function getLastAttempted(): AuthenticatableContract
    {
        return $this->lastAttempted;
    }

    public function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    public function getRecallerName(): string
    {
        return 'remember_' . $this->name . '_' . sha1(static::class);
    }

    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    public function getRememberDuration(): int
    {
        return $this->rememberDuration;
    }

    public function setRememberDuration(int $minutes): self
    {
        $this->rememberDuration = $minutes;

        return $this;
    }

    public function getCookieJar(): QueueingFactoryContract
    {
        if (!$this->cookie) {
            throw new RuntimeException('Cookie jar has not been set.');
        }

        return $this->cookie;
    }

    public function setCookieJar(QueueingFactoryContract $cookie): self
    {
        $this->cookie = $cookie;

        return $this;
    }

    public function getDispatcher(): DispatcherContract
    {
        return $this->events;
    }

    public function setDispatcher(DispatcherContract $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function getSession(): SessionContract
    {
        return $this->session;
    }

    public function getUser(): ?AuthenticatableContract
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(AuthenticatableContract $user)
    {
        $this->user = $user;

        $this->loggedOut = false;

        $this->fireAuthenticatedEvent($user);

        return $this;
    }

    public function getRequest()
    {
        return $this->request ?: Request::createFromGlobals();
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    protected function attemptBasic(Request $request, string $field, array $extraConditions = []): bool
    {
        if (!$request->getUser()) {
            return false;
        }

        return $this->attempt(array_merge(
            $this->basicCredentials($request, $field),
            $extraConditions
        ));
    }

    protected function basicCredentials(Request $request, string $field): array
    {
        return [$field => $request->getUser(), 'password' => $request->getPassword()];
    }

    protected function failedBasicResponse(): void
    {
        throw new UnauthorizedHttpException('Basic', 'Invalid credentials.');
    }

    protected function hasValidCredentials(?AuthenticatableContract $user, array $credentials)
    {
        $validated = $user && $this->userProvider->validateCredentials($user, $credentials);

        if ($validated) {
            $this->fireValidatedEvent($user);
        }

        return $validated;
    }

    protected function shouldLogin(array|callable|null $callbacks, AuthenticatableContract $user): bool
    {
        foreach (Arr::wrap($callbacks) as $callback) {
            if (!$callback($user, $this)) {
                return false;
            }
        }

        return true;
    }

    protected function updateSession($id)
    {
        $this->session->put($this->getName(), $id);

        $this->session->migrate(true);
    }

    protected function createNewToken(): string
    {
        return Str::random(60);
    }

    protected function queueRecallerCookie(AuthenticatableContract $user, string $token): void
    {
        $value = $user->getAuthIdentifier() . '|' . $token . '|' . $user->getAuthPassword();

        $recaller = $this->getCookieJar()
            ->make($this->getRecallerName(), $value, $this->getRememberDuration());

        $this->getCookieJar()->queue($recaller);
    }

    protected function clearUserDataFromStorage(?Recaller $recaller = null): void
    {
        $this->session->remove($this->getName());

        if (!$recaller) {
            return;
        }

        $cookie = $this->getCookieJar()
            ->forget($this->getRecallerName());

        $this->getCookieJar()
            ->queue($cookie);
    }

    protected function tryToGetUserFromRecaller(Recaller $recaller): ?AuthenticatableContract
    {
        if (!$recaller->valid() || $this->recallAttempted) {
            return null;
        }

        $this->recallAttempted = true;

        $user = $this->userProvider->retrieveById($recaller->id());

        if ($user) {
            $token = $this->tokenProvider->retrieveByToken($user, $recaller->token());

            if ($token) {
                $this->viaRemember = true;

                return $this->user = $user;
            }
        }

        return null;
    }

    protected function getRecaller(): ?Recaller
    {
        if (!$this->request) {
            return null;
        }

        if ($recaller = $this->request->cookies->get($this->getRecallerName())) {
            return new Recaller($recaller);
        }

        return null;
    }

    protected function fireCurrentDeviceLogoutEvent(AuthenticatableContract $user): void
    {
        $this->events?->dispatch(new CurrentDeviceLogout($this->name, $user));
    }

    protected function fireAttemptEvent(array $credentials, bool $remember = false): void
    {
        $this->events?->dispatch(new Attempting($this->name, $credentials, $remember));
    }

    protected function fireValidatedEvent(AuthenticatableContract $user): void
    {
        $this->events?->dispatch(new Validated($this->name, $user));
    }

    protected function fireLoginEvent(AuthenticatableContract $user, bool $remember = false): void
    {
        $this->events?->dispatch(new Login($this->name, $user, $remember));
    }

    protected function fireLogoutEvent(AuthenticatableContract $user): void
    {
        $this->events?->dispatch(new Logout($this->name, $user));
    }

    protected function fireAuthenticatedEvent(AuthenticatableContract $user): void
    {
        $this->events?->dispatch(new Authenticated($this->name, $user));
    }

    protected function fireOtherDeviceLogoutEvent(AuthenticatableContract $user): void
    {
        $this->events?->dispatch(new OtherDeviceLogout($this->name, $user));
    }

    protected function fireFailedEvent(AuthenticatableContract $user, array $credentials): void
    {
        $this->events?->dispatch(new Failed($this->name, $user, $credentials));
    }
}
