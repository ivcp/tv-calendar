<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use App\Exception\BadRequestException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\ResponseFormatter;
use App\Services\RequestService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;

class NotFoundExceptionMiddleware implements MiddlewareInterface
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
        } catch (NotFoundException $e) {
            $response = $this->responseFactory->createResponse();
            if ($this->requestService->isXhr($request)) {
                return $this->responseFormatter->asJSONMessage($response, 404, 'page not found');
            }

            throw new HttpNotFoundException($request);
        }
    }
}
