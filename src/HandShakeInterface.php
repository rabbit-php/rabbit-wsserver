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
    public function checkHandshake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;

    public function okHandshake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;

    public function handShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool;
}
