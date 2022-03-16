<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

interface HandShakeInterface
{
    public function checkHandshake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;

    public function okHandshake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;

    public function handShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;
}
