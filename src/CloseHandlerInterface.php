<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

use Swoole\Websocket\Frame;

interface CloseHandlerInterface
{
    public function handle(\Swoole\WebSocket\Server $server, Frame $frame): void;
}
