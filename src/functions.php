<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/8
 * Time: 15:21
 */

if (!function_exists('getClientList')) {
    /**
     * @return array
     */
    function getClientList(): array
    {
        $start_fd = 0;
        $server = \rabbit\App::getServer()->getSwooleServer();
        if (empty($server)) {
            return [];
        }
        $fdList = [];
        foreach ($server->connections as $fd) {
            $fdList[] = $fd;
        }
        return $fdList;
    }
}