<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

use Rabbit\Base\App;
use Swoole\Websocket\Frame;
use Throwable;

/**
 * Class CloseHandler
 * @package Rabbit\WsServer
 */
class CloseHandler implements CloseHandlerInterface
{
    /**
     * @param \Swoole\WebSocket\Server $server
     * @param Frame $frame
     * @throws Throwable
     */
    public function handle(\Swoole\WebSocket\Server $server, Frame $frame): void
    {
        App::warning(sprintf("The fd=%d is closed.code=%s reason=%s!", $frame->fd, $frame->code, $frame->reason));
    }
}
