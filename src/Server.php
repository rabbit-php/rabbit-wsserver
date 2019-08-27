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
use rabbit\memory\table\Table;

/**
 * Class Server
 * @package rabbit\wsserver
 */
class Server extends \rabbit\server\Server
{
    /**
     * @var string
     */
    protected $request;
    /**
     * @var string
     */
    protected $response;

    /** @var string */
    protected $wsRequest = Request::class;
    /** @var string */
    protected $wsResponse = Response::class;
    /** @var HandShakeInterface|null */
    protected $handShake = null;
    /** @var ServerRequestInterface[] */
    protected $requestList = [];
    /** @var CloseHandlerInterface */
    protected $closeHandler = CloseHandler::class;

    /** @var callable */
    protected $errorResponse;
    /** @var Table */
    protected $table;

    /**
     * Server constructor.
     * @param array $setting
     * @param array $coSetting
     * @throws \Exception
     */
    public function __construct(array $setting = [], array $coSetting = [])
    {
        parent::__construct($setting, $coSetting);
        $this->table = new Table('websocket', 8192);
        $this->table->column('path', Table::TYPE_STRING, 100);
        $this->table->create();
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

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
            $this->clearFd($frame->fd);
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
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                        'stack-trace' => explode(PHP_EOL, $throwable->getTraceAsString())
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
        $this->saveFd($request);
    }

    /**
     * @param \Swoole\WebSocket\Server $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    public function onHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        if ($this->handShake->handShake($request, $response)) {
            $this->saveFd($request);
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
        $this->clearFd($fd);
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
//        $server->on('close', [$this, 'onClose']);
        if ($this->handShake) {
            $server->on('handshake', [$this, 'onHandshake']);
        } else {
            $server->on('open', [$this, 'onOpen']);
        }
        $server->start();
    }

    /**
     * @param \Swoole\Http\Request $request
     */
    private function saveFd(\Swoole\Http\Request $request): void
    {
        $this->requestList[$request->fd] = $request;
        $path = '';
        if (isset($request->server['request_uri'])) {
            [$path] = explode('?', $request->server['request_uri']);
        }
        $this->table->set($request->fd, ['path' => $path]);
    }

    /**
     * @param int $fd
     */
    public function clearFd(int $fd): void
    {
        unset($this->requestList[$fd]);
        $this->table->del($fd);
    }
}