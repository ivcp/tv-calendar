<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use App\Exception\RateLimitException;
use App\Exception\ValidationException;
use App\ResponseFormatter;
use App\Services\RequestService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ValidationExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SessionInterface $session,
        private readonly RequestService $requestService,
        private readonly ResponseFormatter $responseFormatter
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RateLimitException $e) {
            $response = $this->responseFactory->createResponse();

            if ($this->requestService->isXhr($request)) {
                return $this->responseFormatter->asJSONMessage(
                    $response
                        ->withStatus(429)
                        ->withHeader('Retry-After', (string) $e->retryAfter)
                        ->withHeader('Cache-Control', 'no-store'),
                    429,
                    $e->getMessage()
                );
            }

            return $this->redirectWithErrors($request, $response, $e->errors);
        } catch (ValidationException $e) {
            $response = $this->responseFactory->createResponse();

            if ($this->requestService->isXhr($request)) {
                return $this->responseFormatter->asJSONErrors($response->withStatus(422), $e->errors);
            }

            return $this->redirectWithErrors($request, $response, $e->errors);
        }
    }

    private function redirectWithErrors(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $errors
    ): ResponseInterface {
        $oldData = (array) $request->getParsedBody();
        $sensitiveFields = ['password', 'confirm_password'];

        $this->session->flash('errors', $errors);
        $this->session->flash('old', array_diff_key($oldData, array_flip($sensitiveFields)));

        return $response
            ->withHeader('Location', $this->requestService->getReferer($request))
            ->withStatus(302);
    }
}
