<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\SessionInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Exception\ValidationException;
use App\Mail\VerificatonEmail;
use App\RequestValidators\LoginUserRequestValidator;
use App\RequestValidators\OauthRequestValidator;
use App\RequestValidators\RegisterUserRequestValidator;
use App\ResponseFormatter;
use App\Services\UserShowsService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class AuthController
{
    private const GOOGLE_OAUTH_STATE_SESSION_KEY  = 'oauth.google.state';
    private const GOOGLE_OAUTH_SHOWS_SESSION_KEY  = 'oauth.google.shows';
    private const GOOGLE_OAUTH_PAYLOAD_LIFETIME   = 600;

    public function __construct(
        private readonly Twig $twig,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
        private readonly AuthInterface $auth,
        private readonly UserShowsService $userShowsService,
        private readonly UserProviderServiceInterface $userProviderService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly VerificatonEmail $verificatonEmail,
        private readonly Config $config,
        private readonly Client $client,
        private readonly SessionInterface $session
    ) {
    }

    public function loginView(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/login.twig');
    }

    public function registerView(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/register.twig');
    }



    public function register(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(RegisterUserRequestValidator::class)
            ->validate($request->getParsedBody());

        $user = $this->auth->register(
            new RegisterUserData(
                email: $data['email'],
                password: $data['password']
            )
        );

        if (isset($data['shows'])) {
            $this->importLocalShows($data['shows'], $user);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }


    public function login(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(LoginUserRequestValidator::class)
            ->validate($request->getParsedBody());


        $user = $this->auth->attemptLogin($data);
        if (! $user) {
            throw new ValidationException(['password' => ['Invalid email or password']]);
        }

        if (isset($data['shows'])) {
            $this->importLocalShows($data['shows'], $user);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');

        if (
            ! hash_equals((string) $user->getId(), $args['id']) ||
            ! hash_equals(sha1($user->getEmail()), $args['hash'])
        ) {
            return $this->twig->render($response, 'auth/verify.twig', ['verified' => false]);
        }

        if (! $user->getVerifiedAt()) {
            $this->userProviderService->verifyUser($user);
        }

        return $this->twig->render($response, 'auth/verify.twig', ['verified' => true]);
    }

    public function resendEmail(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (! $user) {
            return $this->responseFormatter->asJSONMessage($response, 403, 'unauthorized');
        }
        $this->verificatonEmail->send($user);
        return $this->responseFormatter->asJSONMessage($response, 200, 'new verification email sent');
    }

    private function importLocalShows(array $showIds, $user)
    {
        $localList = array_filter($showIds, "is_numeric");
        if (count($localList) > 10) {
            $localList = array_slice($localList, 0, 10);
        }
        $this->userShowsService->addMultipleShows($localList, $user);
    }

    public function googleOauth(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        // Google sends an `error` parameter when the user cancels the flow.
        if (isset($params['error'])) {
            return $this->redirectToLogin($response);
        }

        if (isset($params['code']) && is_string($params['code']) && $params['code'] !== '') {
            return $this->handleGoogleCallback($response, $params);
        }

        return $this->redirectToGoogle($request, $response);
    }

    private function redirectToGoogle(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(OauthRequestValidator::class)
            ->validate($request->getQueryParams());

        $state = bin2hex(random_bytes(32));

        $this->session->put(self::GOOGLE_OAUTH_STATE_SESSION_KEY, $state);
        $this->session->put(
            self::GOOGLE_OAUTH_SHOWS_SESSION_KEY,
            $this->createSignedShowsPayload($data['shows'] ?? [])
        );

        $params = [
            'response_type' => 'code',
            'client_id'     => (string) $this->config->get('oauth.google.client_id'),
            'redirect_uri'  => $this->googleRedirectUri(),
            'scope'         => 'https://www.googleapis.com/auth/userinfo.email',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ];

        return $response
            ->withHeader('Location', 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params))
            ->withStatus(302);
    }

    private function handleGoogleCallback(Response $response, array $params): Response
    {
        $expectedState = $this->session->get(self::GOOGLE_OAUTH_STATE_SESSION_KEY);
        $receivedState = $params['state'] ?? null;

        // The state is single use, regardless of whether the check succeeds.
        $this->session->forget(self::GOOGLE_OAUTH_STATE_SESSION_KEY);

        if (
            ! is_string($expectedState) ||
            $expectedState === '' ||
            ! is_string($receivedState) ||
            ! hash_equals($expectedState, $receivedState)
        ) {
            return $this->redirectToLogin($response);
        }

        $shows = $this->consumeSignedShowsPayload();

        $accessToken = $this->exchangeCodeForAccessToken((string) $params['code']);
        if ($accessToken === null) {
            return $this->redirectToLogin($response);
        }

        $profile = $this->fetchGoogleProfile($accessToken);
        if ($profile === null) {
            return $this->redirectToLogin($response);
        }

        $user = $this->userProviderService->getByCredentials(['email' => $profile['email']]);

        if ($user) {
            $this->auth->login($user);
        } else {
            // `register()` also logs the newly created user in.
            $user = $this->auth->register(
                new RegisterUserData(
                    email: $profile['email'],
                    verified: true
                )
            );
        }

        if (! $user->getVerifiedAt()) {
            $this->userProviderService->verifyUser($user);
        }

        $this->importLocalShows($shows, $user);

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    /**
     * Exchanges the authorization code for an access token.
     *
     * The credentials are sent in the request body (as required by the token endpoint)
     * rather than in the query string, so the client secret does not leak into logs.
     */
    private function exchangeCodeForAccessToken(string $code): ?string
    {
        try {
            $googleResponse = $this->client->post(
                'https://accounts.google.com/o/oauth2/token',
                [
                    'form_params' => [
                        'code'          => $code,
                        'client_id'     => (string) $this->config->get('oauth.google.client_id'),
                        'client_secret' => (string) $this->config->get('oauth.google.client_secret'),
                        'redirect_uri'  => $this->googleRedirectUri(),
                        'grant_type'    => 'authorization_code',
                    ],
                    'http_errors' => false,
                ]
            );
        } catch (GuzzleException) {
            return null;
        }

        if ($googleResponse->getStatusCode() !== 200) {
            return null;
        }

        $body = json_decode((string) $googleResponse->getBody(), true);
        $accessToken = is_array($body) ? ($body['access_token'] ?? null) : null;

        return is_string($accessToken) && $accessToken !== '' ? $accessToken : null;
    }

    private function fetchGoogleProfile(string $accessToken): ?array
    {
        try {
            $googleResponse = $this->client->get(
                'https://www.googleapis.com/oauth2/v3/userinfo',
                [
                    'http_errors' => false,
                    'headers'     => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                ]
            );
        } catch (GuzzleException) {
            return null;
        }

        if ($googleResponse->getStatusCode() !== 200) {
            return null;
        }

        $profile = json_decode((string) $googleResponse->getBody(), true);
        if (! is_array($profile)) {
            return null;
        }

        $email = $profile['email'] ?? null;

        if (
            ! is_string($email) ||
            filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
            filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL) !== true
        ) {
            return null;
        }

        return ['email' => $email];
    }

    private function googleRedirectUri(): string
    {
        return (string) $this->config->get('app_url')
            . (string) $this->config->get('oauth.google.redirect_uri');
    }

    private function redirectToLogin(Response $response): Response
    {
        $this->session->forget(self::GOOGLE_OAUTH_STATE_SESSION_KEY);
        $this->session->forget(self::GOOGLE_OAUTH_SHOWS_SESSION_KEY);

        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    /**
     * Keep the locally stored show IDs in a signed, tamper-proof payload that is
     * independent from the OAuth `state` nonce.
     */
    private function createSignedShowsPayload(array $shows): string
    {
        $showIds = array_values(array_map('intval', array_filter($shows, 'is_numeric')));

        $payload = base64_encode(json_encode([
            'shows' => $showIds,
            'exp'   => time() + self::GOOGLE_OAUTH_PAYLOAD_LIFETIME,
        ], JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->signPayload($payload);
    }

    private function consumeSignedShowsPayload(): array
    {
        $signedPayload = $this->session->get(self::GOOGLE_OAUTH_SHOWS_SESSION_KEY);
        $this->session->forget(self::GOOGLE_OAUTH_SHOWS_SESSION_KEY);

        if (! is_string($signedPayload) || substr_count($signedPayload, '.') !== 1) {
            return [];
        }

        [$payload, $signature] = explode('.', $signedPayload, 2);

        if (! hash_equals($this->signPayload($payload), $signature)) {
            return [];
        }

        $decodedPayload = base64_decode($payload, true);
        if ($decodedPayload === false) {
            return [];
        }

        $data = json_decode($decodedPayload, true);

        if (
            ! is_array($data) ||
            ! isset($data['shows'], $data['exp']) ||
            ! is_array($data['shows']) ||
            ! is_int($data['exp']) ||
            $data['exp'] < time()
        ) {
            return [];
        }

        return $data['shows'];
    }

    private function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) $this->config->get('app_key'));
    }
}
