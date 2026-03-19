<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class JsonResponse extends Response
{
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';

        parent::__construct('', $status, $headers);

        $this->setData($data);
    }

    public function setData(mixed $data): static
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->setContent($json);

        return $this;
    }
}
