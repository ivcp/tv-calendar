<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;

class ResponseFormatter
{
    public function __construct(private readonly Config $config)
    {
    }

    public function asJSON(
        ResponseInterface $response,
        mixed $data,
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode(
            $data,
            $this->config->get('json_tags')
        ));

        return $response;
    }
}
