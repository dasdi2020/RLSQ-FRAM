<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class UploadedFile
{
    public function __construct(
        private readonly string $path,
        private readonly string $originalName,
        private readonly ?string $mimeType = null,
        private readonly ?int $error = UPLOAD_ERR_OK,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getOriginalExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): int|false
    {
        return filesize($this->path);
    }

    public function getError(): int
    {
        return $this->error ?? UPLOAD_ERR_OK;
    }

    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK && is_uploaded_file($this->path);
    }

    public function move(string $directory, ?string $name = null): string
    {
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . ($name ?? $this->originalName);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!move_uploaded_file($this->path, $target)) {
            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier "%s" vers "%s".', $this->path, $target));
        }

        return $target;
    }
}
