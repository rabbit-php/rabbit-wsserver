<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/8
 * Time: 19:44
 */

namespace rabbit\wsserver;

use Psr\Http\Message\ServerRequestInterface;
use rabbit\App;
use rabbit\core\SingletonTrait;
use rabbit\handler\ErrorHandlerInterface;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;
use rabbit\server\Swoole;
use rabbit\server\swoole_websocket_frame;
use rabbit\server\swoole_websocket_server;
use Swoole\WebSocket\CloseFrame;
use swoole_http_server;

/**
 * Class Server
 * @package rabbit\wsserver
 */
class Server extends \rabbit\server\Server
{
    /**
     * @var string
     */
    private $request;
    /**
     * @var string
     */
    private $response;

    /** @var string */
    private $wsRequest = Request::class;
    /** @var string */
    private $wsResponse = Response::class;
    /** @var HandShakeInterface|null */
    private $handShake = null;
    /** @var ServerRequestInterface[] */
    private $requestList = [];
    /** @var CloseHandlerInterface */
    private $closeHandler = CloseHandler::class;

    /** @var callable */
    private $errorResponse;

    /**
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     */
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void
    {
        $psrRequest = $this->request['class'];
        $psrResponse = $this->response['class'];
        $this->dispatcher->dispatch(new $psrRequest($request), new $psrResponse($response));
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Websocket\Frame $frame
     * @throws \Exception
     */
    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\Websocket\Frame $frame): void
    {
        if ($frame->opcode === 0x08) {
            if (is_string($this->closeHandler)) {
                $this->closeHandler = getDI($this->closeHandler);
            }
            unset($this->requestList[$frame->fd]);
            $this->closeHandler->handle($server, $frame);
        } else {
            $psrRequest = $this->wsRequest;
            $psrResponse = $this->wsResponse;

            try {
                $data = JsonHelper::decode($frame->data, true);
                $this->dispatcher->dispatch(new $psrRequest($data, $frame->fd,
                    ArrayHelper::getValue($this->requestList, $frame->fd)),
                    new $psrResponse($server, $frame->fd));
            } catch (\Throwable $throw) {
                try {
                    /**
                     * @var ErrorHandlerInterface $errorHandler
                     */
                    $errorHandler = getDI('errorHandler');
                    $errorHandler->handle($throw)->send();
                } catch (\Throwable $throwable) {
                    $error = [
                        'code' => $throwable->getCode(),
                        'message' => $throwable->getMessage()
                    ];
                    getDI('debug') && $error['error'] = [
                        'stack' => $throwable->getTrace()
                    ];
                    $server->isEstablished($frame->fd) && $server->push($frame->fd, JsonHelper::encode($error));
                }
            }
        }
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     */
    public function onOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request): void
    {
        $this->requestList[$request->fd] = $request;
    }

    /**
     * @param \Swoole\WebSocket\Server $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    public function onHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        if ($this->handShake->handShake($request, $response)) {
            $this->requestList[$request->fd] = $request;
            return true;
        }
        return false;
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $from_id
     * @throws \Exception
     */
    public function onClose(\Swoole\Server $server, int $fd, int $from_id): void
    {
        unset($this->requestList[$fd]);
        $closer = $from_id < 0 ? 'server' : 'customer';
        App::warning(sprintf("The fd=%d is closed by %s!", $fd, $closer));
    }

    /**
     * @return \Swoole\Server
     */
    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\WebSocket\Server($this->host, $this->port, $this->type);
    }

    /**
     * @throws \Exception
     */
    protected function startServer(\Swoole\Server $server = null): void
    {
        parent::startServer($server);
        if (isset($this->setting['open_http_protocol']) && $this->setting['open_http_protocol']) {
            $server->on('request', [$this, 'onRequest']);
        }
        $server->on('message', [$this, 'onMessage']);
        $server->on('close', [$this, 'onClose']);
        if ($this->handShake) {
            $server->on('handshake', [$this, 'onHandshake']);
        } else {
            $server->on('open', [$this, 'onOpen']);
        }
        $server->start();
    }


}