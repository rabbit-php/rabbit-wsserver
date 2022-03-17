<?php

declare(strict_types=1);

namespace Rabbit\WsServer;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Web\AttributeEnum;
use Rabbit\Web\MessageTrait;
use Rabbit\Web\Uri;

class Request implements ServerRequestInterface
{
    use MessageTrait;

    protected ?\Swoole\Http\Request $swooleRequest = null;

    private array $attributes = [];

    private array $cookieParams = [];

    private ?array $parsedBody = null;

    private ?array $bodyParams = null;

    private array $queryParams = [];

    private array $serverParams = [];

    private array $uploadedFiles = [];

    private string $method = 'GET';

    private UriInterface $uri;

    private ?string $requestTarget = null;

    public function __construct(array $data, int $fd, \Swoole\Http\Request $swooleRequest = null)
    {
        $query = $data['query'] ?? [];
        $body = $data['body'] ?? [];
        $this->withQueryParams($query)
            ->withParsedBody($body)
            ->withAttribute(AttributeEnum::CONNECT_FD, $fd);
        if ($swooleRequest) {
            $server = $swooleRequest->server;
            $this->method = strtoupper($server['request_method'] ?? 'GET');
            $this->setHeaders($swooleRequest->header ?? []);
            $this->uri = self::getUriFromGlobals($swooleRequest);
            $this->protocol = isset($server['server_protocol']) ? str_replace(
                'HTTP/',
                '',
                $server['server_protocol']
            ) : '1.1';

            $this->withCookieParams($swooleRequest->cookie ?? [])
                ->withServerParams($server ?? [])
                ->setSwooleRequest($swooleRequest);
        } else {
            $this->uri = new Uri();
        }
        $this->uri->withPath($data['cmd'] ?? '/');
    }

    public function withAttribute($name, $value)
    {
        $clone = $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withParsedBody($data)
    {
        $clone = $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function withQueryParams(array $query)
    {
        $clone = $this;
        $clone->queryParams = $query;
        return $clone;
    }

    private function setHeaders(array $headers): Request
    {
        $this->headers = [];
        foreach ($headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $value = $this->trimHeaderValues($value);
            $normalized = strtolower($header);
            $this->headers[$normalized] = $value;
        }
        return $this;
    }

    private static function getUriFromGlobals(\Swoole\Http\Request $swooleRequest): Uri
    {
        $server = $swooleRequest->server;
        $header = $swooleRequest->header;
        $uri = new Uri();
        $uri = $uri->withScheme(!empty($server['https']) && $server['https'] !== 'off' ? 'https' : 'http');

        $hasPort = false;
        if (isset($server['http_host'])) {
            $hostHeaderParts = explode(':', $server['http_host']);
            $uri = $uri->withHost($hostHeaderParts[0]);
            if (isset($hostHeaderParts[1])) {
                $hasPort = true;
                $uri = $uri->withPort($hostHeaderParts[1]);
            }
        } elseif (isset($server['server_name'])) {
            $uri = $uri->withHost($server['server_name']);
        } elseif (isset($server['server_addr'])) {
            $uri = $uri->withHost($server['server_addr']);
        } elseif (isset($header['host'])) {
            if (\strpos($header['host'], ':')) {
                $hasPort = true;
                list($host, $port) = explode(':', $header['host'], 2);

                if ($port !== '80') {
                    $uri = $uri->withPort($port);
                }
            } else {
                $host = $header['host'];
            }

            $uri = $uri->withHost($host);
        }

        if (!$hasPort && isset($server['server_port'])) {
            $uri = $uri->withPort($server['server_port']);
        }

        $hasQuery = false;
        if (isset($server['request_uri'])) {
            $requestUriParts = explode('?', $server['request_uri']);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($server['query_string'])) {
            $uri = $uri->withQuery($server['query_string']);
        }

        return $uri;
    }

    public function withServerParams(array $serverParams): Request
    {
        $clone = $this;
        $clone->serverParams = $serverParams;
        return $clone;
    }

    public function withCookieParams(array $cookies)
    {
        $clone = $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $clone = $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getBodyParams()
    {
        return $this->bodyParams;
    }

    public function withBodyParams($data): self
    {
        $clone = $this;
        $clone->bodyParams = $data;
        return $clone;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withoutAttribute($name)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $clone = $this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    public function getRequestTarget()
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->uri->getQuery() != '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $clone = $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $method = strtoupper($method);
        $methods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'HEAD'];
        if (!in_array($method, $methods)) {
            throw new \InvalidArgumentException('Invalid Method');
        }
        $clone = $this;
        $clone->method = $method;
        return $clone;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $clone = $this;
        $clone->uri = $uri;

        if (!$preserveHost) {
            $clone->updateHostFromUri();
        }

        return $clone;
    }

    private function updateHostFromUri(): void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if ($this->hasHeader('host')) {
            $header = $this->getHeaderLine('host');
        } else {
            $header = 'Host';
        }
        // Ensure Host is the first header.
        $this->headers = [$header => [$host]] + $this->headers;
    }

    public function getSwooleRequest(): \Swoole\Http\Request
    {
        return $this->swooleRequest;
    }

    public function setSwooleRequest(\Swoole\Http\Request $swooleRequest): Request
    {
        $this->swooleRequest = $swooleRequest;
        return $this;
    }
}
