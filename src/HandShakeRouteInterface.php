<?php


namespace rabbit\wsserver;

/**
 * Interface HandShakeRouteInterface
 * @package rabbit\wsserver
 */
interface HandShakeRouteInterface
{
    /**
     * @param \rabbit\httpserver\Request $request
     * @return bool
     */
    public function check(\rabbit\httpserver\Request $request):bool ;
}
