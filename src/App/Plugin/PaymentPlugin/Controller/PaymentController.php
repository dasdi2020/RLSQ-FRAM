<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Controller;

use App\Plugin\PaymentPlugin\Service\GatewayFactory;
use App\Plugin\PaymentPlugin\Service\PaymentService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/payments')]
class PaymentController extends AbstractController
{
    private function getService(string $tenantSlug): PaymentService
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) { throw new \RuntimeException('Tenant introuvable.'); }
        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        return new PaymentService($factory->getConnection($ctx));
    }

    #[Route('', name: 'payments_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les paiements', tags: ['Payments'])]
    public function list(string $tenantSlug, Request $request): JsonResponse
    {
        $service = $this->getService($tenantSlug);
        return $this->json($service->getPayments(
            (int) ($request->query->get('page') ?? 1),
            (int) ($request->query->get('per_page') ?? 20),
            ['status' => $request->query->get('status'), 'module_source' => $request->query->get('module_source')],
        ));
    }

    #[Route('/stats', name: 'payments_stats', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Statistiques des paiements', tags: ['Payments'])]
    public function stats(string $tenantSlug): JsonResponse
    {
        return $this->json(['data' => $this->getService($tenantSlug)->getStats()]);
    }

    #[Route('/checkout', name: 'payments_checkout', methods: ['POST'])]
    #[ApiRoute(summary: 'Initier un paiement', tags: ['Payments'])]
    public function checkout(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $data['member_id'] = $request->attributes->get('_user_id');
        try {
            return $this->json($this->getService($tenantSlug)->createCheckout($data), 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}', name: 'payments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Détail d\'un paiement', tags: ['Payments'])]
    public function show(string $tenantSlug, int $id): JsonResponse
    {
        $p = $this->getService($tenantSlug)->getPayment($id);
        return $p ? $this->json(['data' => $p]) : $this->json(['error' => 'Paiement introuvable.'], 404);
    }

    #[Route('/{id}/confirm', name: 'payments_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Confirmer un paiement', tags: ['Payments'])]
    public function confirm(string $tenantSlug, int $id): JsonResponse
    {
        try {
            return $this->json(['data' => $this->getService($tenantSlug)->confirm($id)]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}/refund', name: 'payments_refund', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Rembourser un paiement', tags: ['Payments'])]
    public function refund(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $refund = $this->getService($tenantSlug)->refund(
                $id,
                (float) ($data['amount'] ?? 0),
                $data['reason'] ?? '',
                $request->attributes->get('_user_id'),
            );
            return $this->json(['data' => $refund]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}/refunds', name: 'payments_refunds', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Historique des remboursements', tags: ['Payments'])]
    public function refunds(string $tenantSlug, int $id): JsonResponse
    {
        return $this->json(['data' => $this->getService($tenantSlug)->getRefunds($id)]);
    }

    // --- Gateway Config ---

    #[Route('/config', name: 'payments_config_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les configs de gateway', tags: ['Payments'])]
    public function configList(string $tenantSlug): JsonResponse
    {
        return $this->json([
            'data' => $this->getService($tenantSlug)->getGatewayConfigs(),
            'available_gateways' => GatewayFactory::getAvailableGateways(),
        ]);
    }

    #[Route('/config', name: 'payments_config_save', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Configurer un gateway', tags: ['Payments'])]
    public function configSave(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json(['data' => $this->getService($tenantSlug)->saveGatewayConfig($data)]);
    }

    // --- Subscriptions ---

    #[Route('/subscriptions', name: 'subscriptions_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les abonnements', tags: ['Payments'])]
    public function subscriptions(string $tenantSlug, Request $request): JsonResponse
    {
        return $this->json($this->getService($tenantSlug)->getSubscriptions(
            (int) ($request->query->get('page') ?? 1),
        ));
    }

    #[Route('/subscriptions', name: 'subscriptions_create', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Créer un abonnement', tags: ['Payments'])]
    public function createSubscription(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->json(['data' => $this->getService($tenantSlug)->createSubscription($data)], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/subscriptions/{id}/cancel', name: 'subscriptions_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Annuler un abonnement', tags: ['Payments'])]
    public function cancelSubscription(string $tenantSlug, int $id): JsonResponse
    {
        try {
            return $this->json(['data' => $this->getService($tenantSlug)->cancelSubscription($id)]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    // --- Webhooks ---

    #[Route('/webhooks/{gateway}', name: 'payments_webhook', methods: ['POST'])]
    #[ApiRoute(summary: 'Recevoir un webhook de paiement', tags: ['Payments'])]
    public function webhook(string $tenantSlug, string $gateway, Request $request): JsonResponse
    {
        $service = $this->getService($tenantSlug);
        $configs = $service->getGatewayConfigs();
        $config = null;

        foreach ($configs as $c) {
            if (($c['gateway'] ?? '') === $gateway) {
                $config = $c;
                break;
            }
        }

        if (!$config) {
            return $this->json(['error' => 'Gateway non configuré.'], 404);
        }

        try {
            $gw = GatewayFactory::create($gateway, json_decode($config['credentials'] ?? '{}', true));
            $event = $gw->handleWebhook($request->getContent());

            return $this->json(['received' => true, 'event_type' => $event['event_type']]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
