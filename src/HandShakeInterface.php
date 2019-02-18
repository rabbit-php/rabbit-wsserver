<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/16
 * Time: 14:49
 */

namespace rabbit\wsserver;

/**
 * Interface HandShakeInterface
 * @package rabbit\wsserver
 */
interface HandShakeInterface
{
    public function okHandshake(\swoole_http_request $request, \swoole_http_response $response): bool;
}