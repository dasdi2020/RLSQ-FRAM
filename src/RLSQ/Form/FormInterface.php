<?php

declare(strict_types=1);

namespace RLSQ\Form;

use RLSQ\HttpFoundation\Request;

interface FormInterface
{
    public function handleRequest(Request $request): void;

    public function isSubmitted(): bool;

    public function isValid(): bool;

    public function getData(): mixed;

    /**
     * @return string[][]
     */
    public function getErrors(): array;

    public function createView(): FormView;

    public function getName(): string;
}
