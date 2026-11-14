<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

class CsrfFieldsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ContainerInterface $container,
        private readonly SessionInterface $session
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->twig->getEnvironment()->addGlobal('csrf', $this->fields());

        return $handler->handle($request);
    }

    /**
     * Anonymous requests have no session, so there is no token to render. The
     * global is still registered with empty values: templates reference it, and
     * guests only mutate state through localStorage.
     *
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        if (! $this->session->isActive()) {
            return [
                'keys' => [
                    'name'  => '',
                    'value' => '',
                ],
                'name'    => '',
                'value'   => '',
                'fields'  => '',
            ];
        }

        $csrf = $this->container->get('csrf');
        $csrfNameKey = $csrf->getTokenNameKey();
        $csrfValueKey = $csrf->getTokenValueKey();
        $csrfName = $csrf->getTokenName();
        $csrfValue = $csrf->getTokenValue();

        return [
            'keys' => [
                'name'  => $csrfNameKey,
                'value' => $csrfValueKey,
            ],
            'name'  => $csrfName,
            'value' => $csrfValue,
            'fields' => <<<CSRF_Fields
                <input
                    type="hidden"
                    name="$csrfNameKey"
                    value="$csrfName"
                  />
                  <input
                    type="hidden"
                    name="$csrfValueKey"
                    value="$csrfValue"
                  />
                CSRF_Fields,
        ];
    }
}