<?php

declare(strict_types=1);

namespace Rabbit\WsServer;

use Rabbit\Base\App;
use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\FileHelper;
use Rabbit\Base\Helper\JsonHelper;
use Rabbit\Base\Table\Table;
use Rabbit\HttpServer\Middleware\ReqHandlerMiddleware;
use Rabbit\HttpServer\Request;
use Rabbit\HttpServer\Response;
use Rabbit\Server\Server as ServerServer;
use Rabbit\Server\ServerDispatcher;
use Rabbit\Web\RequestContext;
use Rabbit\Web\RequestHandler;
use Rabbit\Web\ResponseContext;
use Rabbit\WsServer\Response as WsServerResponse;
use Swoole\Websocket\Frame;
use Throwable;

class Server extends ServerServer implements InitInterface
{
    protected array $middlewares = [];
    protected ?HandShakeInterface $handShake = null;
    protected array $requestList = [];
    protected string|CloseHandler $closeHandler = CloseHandler::class;
    protected Table $table;

    public function __construct(array $setting = [], array $coSetting = [])
    {
        parent::__construct($setting, $coSetting);
        $this->table = new Table('websocket', 8192);
        $this->table->column('path', Table::TYPE_STRING, 100);
        $this->table->create();
    }

    public function init(): void
    {
        if (!$this->dispatcher) {
            $this->dispatcher = create(ServerDispatcher::class, [
                'requestHandler' => create(RequestHandler::class, [
                    'middlewares' => $this->middlewares ? array_values($this->middlewares) : [
                        create(ReqHandlerMiddleware::class)
                    ]
                ])
            ]);
        }
        if (!is_dir(dirname($this->setting['log_file']))) {
            FileHelper::createDirectory(dirname($this->setting['log_file']));
        }
        unset($this->middlewares);
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void
    {
        try {
            $data = [
                'server' => $request->server,
                'header' => $request->header,
                'query' => $request->get,
                'body' => $request->post,
                'content' => $request->rawContent(),
                'cookie' => $request->cookie,
                'files' => $request->files,
                'fd' => $request->fd,
                'request' => $request,
            ];
            $psrRequest = new Request($data);
            $psrResponse = new Response();
            RequestContext::set($psrRequest);
            ResponseContext::set($psrResponse);
            $this->dispatcher->dispatch($psrRequest)->setSwooleResponse($response)->send();
        } catch (Throwable $throw) {
            $errorResponse = service('errorResponse', false);
            if ($errorResponse === null) {
                $response->status(500);
                $response->end("An internal server error occurred.");
            } else {
                $response->end($errorResponse->handle($throw, $response));
            }
        }
    }

    public function onMessage(\Swoole\WebSocket\Server $server, Frame $frame): void
    {
        if ($frame->opcode === 0x08) {
            if (is_string($this->closeHandler)) {
                $this->closeHandler = service($this->closeHandler);
            }
            $this->clearFd($frame->fd);
            $this->closeHandler->handle($server, $frame);
        } else {
            try {
                $param = JsonHelper::decode($frame->data, true);
                $request = ArrayHelper::getValue($this->requestList, (string)$frame->fd);
                $data = [
                    'server' => $request?->server,
                    'header' => $request?->header,
                    'query' => $param['query'] ?? [],
                    'body' => $param['body'] ?? [],
                    'content' => $request?->rawContent(),
                    'cookie' => $request?->cookie,
                    'files' => $request?->files,
                    'fd' => $frame->fd,
                    'request' => $request,
                ];
                $psrRequest = new Request($data);
                $psrResponse = new WsServerResponse($server, $frame->fd);
                RequestContext::set($psrRequest);
                ResponseContext::set($psrResponse);
                $this->dispatcher->dispatch($psrRequest)->send();
            } catch (Throwable $throw) {
                try {
                    $errorHandler = service('errorHandler');
                    $errorHandler->handle($throw, $psrResponse);
                    $psrResponse->send();
                } catch (Throwable $throwable) {
                    $error = [
                        'code' => $throwable->getCode(),
                        'message' => $throwable->getMessage()
                    ];
                    config('debug') && $error['error'] = [
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                        'stack-trace' => explode(PHP_EOL, $throwable->getTraceAsString())
                    ];
                    $server->isEstablished($frame->fd) && $server->push($frame->fd, JsonHelper::encode($error));
                }
            }
        }
    }

    public function onOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request): void
    {
        $this->saveFd($request);
    }

    public function onHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        if ($this->handShake->handShake($request, $response)) {
            $this->saveFd($request);
            return true;
        }
        return false;
    }

    public function onClose(\Swoole\Server $server, int $fd, int $from_id): void
    {
        $this->clearFd($fd);
        $closer = $from_id < 0 ? 'server' : 'customer';
        App::warning(sprintf("The fd=%d is closed by %s!", $fd, $closer));
    }

    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\WebSocket\Server($this->host, $this->port, $this->type);
    }

    protected function startServer(\Swoole\Server $server = null): void
    {
        parent::startServer($server);
        if (isset($this->setting['open_http_protocol']) && $this->setting['open_http_protocol']) {
            $server->on('request', [$this, 'onRequest']);
        }
        $server->on('message', [$this, 'onMessage']);
        if ($this->handShake) {
            $server->on('handshake', [$this, 'onHandshake']);
        } else {
            $server->on('open', [$this, 'onOpen']);
        }
        $server->start();
    }

    private function saveFd(\Swoole\Http\Request $request): void
    {
        $this->requestList[$request->fd] = $request;
        $path = '';
        if (isset($request->server['request_uri'])) {
            [$path] = explode('?', $request->server['request_uri']);
        }
        $this->table->set((string)$request->fd, ['path' => $path]);
    }

    public function clearFd(int $fd): void
    {
        unset($this->requestList[$fd]);
        $this->table->del((string)$fd);
    }
}
