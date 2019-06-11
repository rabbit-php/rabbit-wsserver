<?php


namespace rabbit\wsserver;

/**
 * Interface CloseHandlerInterface
 * @package rabbit\wsserver
 */
interface CloseHandlerInterface
{
    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Websocket\Frame $frame
     */
    public function handle(\Swoole\WebSocket\Server $server, \Swoole\Websocket\Frame $frame): void;
}