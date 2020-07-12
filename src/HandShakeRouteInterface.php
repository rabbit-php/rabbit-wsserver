<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

/**
 * Interface HandShakeRouteInterface
 * @package Rabbit\WsServer
 */
interface HandShakeRouteInterface
{
    /**
     * @param \Rabbit\HttpServer\Request $request
     * @return bool
     */
    public function check(\Rabbit\HttpServer\Request $request):bool ;
}
