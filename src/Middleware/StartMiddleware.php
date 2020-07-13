<?php
declare(strict_types=1);

namespace Rabbit\WsServer\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\JsonHelper;
use Rabbit\Web\AttributeEnum;

/**
 * Class StartMiddleware
 * @package Rabbit\WsServer\Middleware
 */
class StartMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $data = $response->getAttribute(AttributeEnum::RESPONSE_ATTRIBUTE);
        $data = ['data' => $data];
        $data = ArrayHelper::toArray($data);
        $content = JsonHelper::encode($data, JSON_UNESCAPED_UNICODE);
        $response = $response->withContent($content);
        return $response;
    }
}
