<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\AuthInterface;
use App\ResponseFormatter;
use App\Services\RequestService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly AuthInterface $auth,
        private readonly RequestService $requestService,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->auth->user()) {
            return $handler->handle($request);
        }

        if ($this->requestService->isXhr($request)) {
            $response = $this->responseFactory->createResponse();
            return $this->responseFormatter->asJSONMessage($response, 403, 'log in to perform action');
        }

        return $this->responseFactory->createResponse(302)->withHeader('Location', '/login');
    }
}
