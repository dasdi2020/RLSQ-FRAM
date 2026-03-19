<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class RedirectResponse extends Response
{
    private string $targetUrl;

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        if ($status < 300 || $status >= 400) {
            throw new \InvalidArgumentException(sprintf('Le code HTTP %d n\'est pas un code de redirection.', $status));
        }

        $this->targetUrl = $url;

        $headers['Location'] = $url;

        parent::__construct('', $status, $headers);

        $this->setContent(sprintf(
            '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=\'%s\'"></head><body><a href="%s">%s</a></body></html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        ));
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
}
