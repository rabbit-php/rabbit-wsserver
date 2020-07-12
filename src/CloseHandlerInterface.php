<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

use Swoole\Websocket\Frame;

/**
 * Interface CloseHandlerInterface
 * @package Rabbit\WsServer
 */
interface CloseHandlerInterface
{
    /**
     * @param \Swoole\WebSocket\Server $server
     * @param Frame $frame
     */
    public function handle(\Swoole\WebSocket\Server $server, Frame $frame): void;
}
