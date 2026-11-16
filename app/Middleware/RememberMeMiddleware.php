<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\AuthInterface;
use App\Contracts\RememberMeInterface;
use App\Contracts\SessionInterface;
use App\Contracts\UserInterface;
use App\Services\RequestService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resumes a session from the remember-me cookie, upgrades sessions that predate
 * the feature, and sends a lapsed remembered user to the login page instead of
 * silently dropping them into guest mode.
 */
class RememberMeMiddleware implements MiddlewareInterface
{
    /**
     * Pages that must stay reachable with a stale cookie: the login flow itself
     * and URLs opened from emails or crawlers.
     */
    private const PUBLIC_PATHS = [
        '/login',
        '/register',
        '/forgot-password',
        '/google-oauth',
        '/sitemap.xml',
        '/robots.txt',
    ];

    private const PUBLIC_PATH_PREFIXES = [
        '/reset-password/',
        '/verify/',
    ];

    public function __construct(
        private readonly AuthInterface $auth,
        private readonly RememberMeInterface $rememberMe,
        private readonly SessionInterface $session,
        private readonly RequestService $requestService,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->auth->user() && $this->rememberMe->hasCookie()) {
            // No session user, but the browser still carries a remember cookie.
            $user = $this->rememberMe->verify(false);

            if ($user) {
                $this->resume($user);
            } elseif ($this->shouldSendToLogin($request)) {
                // The cookie outlives its signed validity by a grace period, so
                // this is a remembered user whose login lapsed (or was revoked).
                return $this->sendToLogin();
            }
            // Otherwise keep the cookie: a normal page navigation will use it
            // to explain what happened.
        } elseif ($this->auth->user() && $this->session->get('_remember') === null) {
            // Session created before remember-me existed: honour it and issue
            // the long-lived cookie. New logins always write a real marker, so
            // an explicitly unchecked login never ends up here.
            $this->upgradeLegacySession();
        }

        return $handler->handle($request);
    }

    private function resume(UserInterface $user): void
    {
        // The session cookie may have expired while the remember cookie did
        // not, so there might be no session at all.
        if (! $this->session->isActive()) {
            $this->session->start();
        }

        $this->auth->restoreSession($user);
    }

    private function upgradeLegacySession(): void
    {
        $user = $this->auth->user();
        if (! $user) {
            return;
        }

        $this->rememberMe->issue($user);

        // Only claim the session is remembered when a cookie was really issued
        // (issue() is a no-op when APP_KEY is missing).
        if ($this->rememberMe->hasCookie()) {
            $this->session->put('_remember', true);
        }
    }

    private function sendToLogin(): ResponseInterface
    {
        // The cookie is already invalid; drop it so the redirect happens once.
        $this->rememberMe->clear();

        $this->session->flash('notice', [
            'Your login has expired. Please log in again.',
        ]);

        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', '/login');
    }

    /**
     * Only plain page navigations are redirected: a POST must reach the login
     * form handler, XHR cannot consume an HTML redirect, and auth pages must not
     * loop back to themselves.
     */
    private function shouldSendToLogin(ServerRequestInterface $request): bool
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($this->requestService->isXhr($request)) {
            return false;
        }

        $path = $request->getUri()->getPath();

        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return false;
        }

        foreach (self::PUBLIC_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
