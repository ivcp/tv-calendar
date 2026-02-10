<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

class ValidateSignatureMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Twig $twig
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $queryParams = $request->getQueryParams();
        $originalSignature = $queryParams['signature'] ?? '';
        $expiration = (int)($queryParams['expiration'] ?? 0);
        unset($queryParams['signature']);
        $url = (string) $uri->withQuery(http_build_query($queryParams));

        $signature = hash_hmac('sha256', $url, $this->config->get('app_key'));

        if ($expiration <= time() || !hash_equals($signature, $originalSignature)) {
            if (!hash_equals($signature, $originalSignature)) {
                error_log(
                    sprintf('ERROR Signature mismatch: signature=%s original=%s', $signature, $originalSignature)
                );
            }
            if ($expiration <= time()) {
                error_log('ERROR Link Expired');
            }
            $response = $this->responseFactory->createResponse();
            return $this->twig->render($response, 'auth/verify.twig', ['verified' => false]);
        };

        return $handler->handle($request);
    }
}
