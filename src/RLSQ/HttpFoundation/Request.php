<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

use RLSQ\HttpFoundation\Session\SessionInterface;

class Request
{
    public ParameterBag $query;       // $_GET
    public ParameterBag $request;     // $_POST
    public ParameterBag $attributes;  // Paramètres de route, données internes
    public ParameterBag $cookies;     // $_COOKIE
    public ServerBag $server;         // $_SERVER
    public FileBag $files;            // $_FILES
    public HeaderBag $headers;

    protected ?string $content = null;
    protected ?string $method = null;
    protected ?string $pathInfo = null;
    private ?SessionInterface $session = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ) {
        $this->query = new ParameterBag($query);
        $this->request = new ParameterBag($request);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->content = $content;
    }

    public static function createFromGlobals(): static
    {
        return new static(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            null,
        );
    }

    /**
     * Crée une Request à partir de paramètres explicites (utile pour les tests).
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ): static {
        $parsedUrl = parse_url($uri);

        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => strtoupper($method),
            'PATH_INFO' => $parsedUrl['path'] ?? '/',
            'QUERY_STRING' => $parsedUrl['query'] ?? '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], $server);

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $isPostLike = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        return new static(
            $isPostLike ? $query : array_merge($query, $parameters),
            $isPostLike ? $parameters : [],
            [],
            $cookies,
            $files,
            $server,
            $content,
        );
    }

    public function getMethod(): string
    {
        if ($this->method !== null) {
            return $this->method;
        }

        $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));

        // Support du _method pour les formulaires HTML (PUT, DELETE, PATCH)
        if ($this->method === 'POST') {
            $override = $this->request->get('_method') ?? $this->headers->get('X-HTTP-Method-Override');
            if ($override !== null) {
                $this->method = strtoupper((string) $override);
            }
        }

        return $this->method;
    }

    public function getPathInfo(): string
    {
        if ($this->pathInfo !== null) {
            return $this->pathInfo;
        }

        $requestUri = $this->server->get('REQUEST_URI', '/');
        $this->pathInfo = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        return $this->pathInfo;
    }

    public function getContent(): string
    {
        if ($this->content === null) {
            $this->content = file_get_contents('php://input') ?: '';
        }

        return $this->content;
    }

    public function getHost(): string
    {
        return $this->headers->get('host', $this->server->get('SERVER_NAME', 'localhost'));
    }

    public function getPort(): int
    {
        return (int) $this->server->get('SERVER_PORT', '80');
    }

    public function getScheme(): string
    {
        $https = $this->server->get('HTTPS', '');

        return ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    public function getUri(): string
    {
        $scheme = $this->getScheme();
        $host = $this->getHost();
        $port = $this->getPort();
        $path = $this->getPathInfo();
        $qs = $this->server->get('QUERY_STRING', '');

        $uri = $scheme . '://' . $host;

        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $uri .= ':' . $port;
        }

        $uri .= $path;

        if ($qs !== '') {
            $uri .= '?' . $qs;
        }

        return $uri;
    }

    public function getQueryString(): string
    {
        return $this->server->get('QUERY_STRING', '');
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function isXmlHttpRequest(): bool
    {
        return $this->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    public function getSession(): SessionInterface
    {
        if ($this->session === null) {
            throw new \LogicException('Aucune session n\'a été définie sur cette requête.');
        }

        return $this->session;
    }

    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }

    public function getClientIp(): ?string
    {
        return $this->headers->get('X-Forwarded-For')
            ?? $this->server->get('REMOTE_ADDR');
    }
}
