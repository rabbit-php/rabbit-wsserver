<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

use Rabbit\Base\App;
use Swoole\Websocket\Frame;
use Throwable;

class CloseHandler implements CloseHandlerInterface
{
    public function handle(\Swoole\WebSocket\Server $server, Frame $frame): void
    {
        App::warning(sprintf("The fd=%d is closed.code=%s reason=%s!", $frame->fd, $frame->code, $frame->reason));
    }
}
