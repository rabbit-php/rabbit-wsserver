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
        $server = \rabbit\App::getServer();
        $fdList = [];
        while (true) {
            $conn_list = $server->getClientList($start_fd, 10);
            if ($conn_list === false or count($conn_list) === 0) {
                break;
            }
            $start_fd = end($conn_list);
            foreach ($conn_list as $fd) {
                $fdList[] = $fd;
            }
        }
        return $fdList;
    }
}