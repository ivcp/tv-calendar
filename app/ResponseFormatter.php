<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;

class ResponseFormatter
{
    public function __construct(private readonly Config $config)
    {
    }

    public function asJSONErrors(
        ResponseInterface $response,
        mixed $data,
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(
            ['errors' => $data],
            $this->config->get('json_tags')
        ));

        return $response;
    }

    public function asJSON(
        ResponseInterface $response,
        int $status,
        string $msg,
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        $response->getBody()->write(json_encode(
            ['msg' => $msg],
            $this->config->get('json_tags')
        ));

        return $response;
    }


}
