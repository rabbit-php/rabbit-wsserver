<?php

declare(strict_types=1);

namespace Rabbit\WsServer;

use Psr\Http\Message\ResponseInterface;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Web\MessageTrait;

class Response implements ResponseInterface
{
    use MessageTrait;

    const FD_LIST = 'fdList';

    private array $attributes = [];

    private int $fd;

    private \Swoole\Server $server;

    private int $statusCode = 200;

    private string $charset = 'utf-8';

    public function __construct(\Swoole\Server $server, int $fd)
    {
        $this->server = $server;
        $this->fd = $fd;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        $this->statusCode = (int)$code;
        return $this;
    }

    public function getReasonPhrase()
    {
        throw new NotSupportedException("can not call " . __METHOD__);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute($name, $value): Response
    {
        $this->attributes[$name] = $value;
        return $this;
    }

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
            rgo(function () use ($fd, $message): void {
                $this->server->isEstablished($fd) && $this->server->push($fd, $message);
            });
        }
        rgo(function (): void {
            $this->server->isEstablished($this->fd) && $this->server->push($this->fd, $this->stream);
        });
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function withCharset(string $charset): Response
    {
        $this->charset = $charset;
        return $this;
    }
}
