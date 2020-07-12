<?php
declare(strict_types=1);

namespace Rabbit\WsServer;

use Psr\Http\Message\ResponseInterface;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Web\MessageTrait;

/**
 * Class Response
 * @package Rabbit\WsServer
 */
class Response implements ResponseInterface
{
    use MessageTrait;

    const FD_LIST = 'fdList';
    /**
     * @var array
     */
    private array $attributes = [];
    /**
     * @var int
     */
    private int $fd;
    /**
     * @var \Swoole\Server
     */
    private \Swoole\Server $server;
    /**
     * @var int
     */
    private int $statusCode = 200;
    /**
     * @var string
     */
    private string $charset = 'utf-8';

    /**
     * Response constructor.
     * @param \Swoole\Server $server
     * @param int $fd
     */
    public function __construct(\Swoole\Server $server, int $fd)
    {
        $this->server = $server;
        $this->fd = $fd;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return mixed|static
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $this->statusCode = (int)$code;
        return $this;
    }

    /**
     * @return string|void
     * @throws NotSupportedException
     */
    public function getReasonPhrase()
    {
        throw new NotSupportedException("can not call " . __METHOD__);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * @param $name
     * @param $value
     * @return Response
     */
    public function withAttribute($name, $value): Response
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * @param $content
     * @return Response
     */
    public function withContent($content): Response
    {
        if ($this->stream) {
            return $this;
        }

        $this->stream = $content;
        return $this;
    }

    public function send(): void
    {
        $fdList = ArrayHelper::getValue($this->attributes, static::FD_LIST, []);
        foreach ($fdList as $fd => $message) {
            rgo(function () use ($fd, $message) {
                $this->server->isEstablished($fd) && $this->server->push($fd, $message);
            });
        }
        rgo(function () {
            $this->server->isEstablished($this->fd) && $this->server->push($this->fd, $this->stream);
        });
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     * @return Response
     */
    public function withCharset(string $charset): Response
    {
        $this->charset = $charset;
        return $this;
    }
}
