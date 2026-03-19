<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class Response
{
    protected string $content;
    protected int $statusCode;
    public HeaderBag $headers;

    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $status;
        $this->headers = new HeaderBag($headers);
    }

    public function send(): static
    {
        $this->sendHeaders();
        $this->sendContent();

        return $this;
    }

    public function sendHeaders(): static
    {
        if (headers_sent()) {
            return $this;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers->all() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                header($name . ': ' . $value, $replace);
                $replace = false;
            }
        }

        return $this;
    }

    public function sendContent(): static
    {
        echo $this->content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;

        return $this;
    }
}
