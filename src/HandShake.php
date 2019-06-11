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
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     * @return bool
     */
    public function checkHandshake(\swoole_http_request $request, \swoole_http_response $response): bool
    {
        return true;
    }

}