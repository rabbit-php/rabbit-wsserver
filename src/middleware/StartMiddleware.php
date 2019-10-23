<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/15
 * Time: 14:59
 */

namespace rabbit\wsserver\middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;
use rabbit\server\AttributeEnum;

/**
 * Class StartMiddleware
 * @package rabbit\httpserver\middleware
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
