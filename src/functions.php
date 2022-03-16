<?php
declare(strict_types=1);

if (!function_exists('getClientList')) {
    function getClientList(): array
    {
        $server = \Rabbit\Server\ServerHelper::getServer()->getSwooleServer();
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
