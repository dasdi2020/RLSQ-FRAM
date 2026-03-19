<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

interface HttpKernelInterface
{
    public function handle(Request $request): Response;
}
