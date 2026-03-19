<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel;

final class KernelEvents
{
    public const REQUEST = 'kernel.request';
    public const CONTROLLER = 'kernel.controller';
    public const CONTROLLER_ARGUMENTS = 'kernel.controller_arguments';
    public const VIEW = 'kernel.view';
    public const RESPONSE = 'kernel.response';
    public const EXCEPTION = 'kernel.exception';
    public const TERMINATE = 'kernel.terminate';
}
