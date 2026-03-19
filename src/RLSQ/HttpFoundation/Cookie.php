<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class Cookie
{
    public function __construct(
        private readonly string $name,
        private readonly string $value = '',
        private readonly int $expire = 0,
        private readonly string $path = '/',
        private readonly string $domain = '',
        private readonly bool $secure = false,
        private readonly bool $httpOnly = true,
        private readonly ?string $sameSite = 'Lax',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpire(): int
    {
        return $this->expire;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    public function send(): void
    {
        setcookie($this->name, $this->value, [
            'expires' => $this->expire,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite ?? '',
        ]);
    }

    public function __toString(): string
    {
        $parts = [urlencode($this->name) . '=' . urlencode($this->value)];

        if ($this->expire !== 0) {
            $parts[] = 'expires=' . gmdate('D, d M Y H:i:s T', $this->expire);
        }
        if ($this->path !== '') {
            $parts[] = 'path=' . $this->path;
        }
        if ($this->domain !== '') {
            $parts[] = 'domain=' . $this->domain;
        }
        if ($this->secure) {
            $parts[] = 'secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'httponly';
        }
        if ($this->sameSite !== null) {
            $parts[] = 'samesite=' . $this->sameSite;
        }

        return implode('; ', $parts);
    }
}
