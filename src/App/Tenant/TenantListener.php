<?php

declare(strict_types=1);

namespace App\Tenant;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\KernelEvents;

/**
 * Listener kernel.request qui résout le tenant et le place dans le TenantContext.
 * Priorité 56 : après JWT (48) mais avant Router (32).
 */
class TenantListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $tenant = $this->resolver->resolve($request);

        if ($tenant !== null) {
            $this->context->setTenant($tenant);
            $request->attributes->set('_tenant', $tenant);
            $request->attributes->set('_tenant_id', (int) $tenant['id']);
            $request->attributes->set('_tenant_slug', $tenant['slug']);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 56],
        ];
    }
}
