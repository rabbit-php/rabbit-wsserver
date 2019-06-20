<?php


namespace rabbit\wsserver;

/**
 * Class HandShake
 * @package rabbit\wsserver
 */
class HandShake implements HandShakeInterface
{
    use HandShakeTrait;

    /**
     * @param \Swoole\Http\Request $request
     * @param Swoole\Http\Response $response
     * @return bool
     */
    public function checkHandshake(\Swoole\Http\Request $request, Swoole\Http\Response $response): bool
    {
        return true;
    }

}