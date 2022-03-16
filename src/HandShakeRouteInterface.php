<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

interface HandShakeRouteInterface
{
    public function check(\Rabbit\HttpServer\Request $request):bool ;
}
