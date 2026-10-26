<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\EvenementWebhook;
use App\Enum\PrestatairePaiement;
use App\Message\TraiterNotificationPaiement;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Notification de Mollie : POST id=tr_… (formulaire), sans signature.
 *
 * Rien n'est cru sur parole : la notification est seulement enregistrée, puis le statut est relu
 * chez Mollie en arrière-plan (TraiterNotificationPaiementHandler). La réponse est toujours 200,
 * même pour un identifiant inconnu, pour ne rien révéler et éviter les renvois inutiles.
 * Route publique, sans jeton CSRF (appelée de serveur à serveur).
 */
#[AsController]
final readonly class WebhookPaiementController
{
    public function __construct(
        private PaiementRepository $paiementRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        #[Autowire(service: 'limiter.webhook_paiement')]
        private RateLimiterFactoryInterface $webhookLimiter,
    ) {
    }

    #[Route('/api/webhooks/mollie', name: 'webhook_mollie', methods: ['POST'])]
    public function mollie(Request $request): Response
    {
        if (!$this->webhookLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new Response(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $id = $request->request->get('id');
        if (!\is_string($id) || 1 !== preg_match('/^tr_[A-Za-z0-9]{4,40}$/', $id)) {
            return new Response(null, Response::HTTP_OK);
        }
        if (null === $this->paiementRepository->findOneBy(['prestataire' => PrestatairePaiement::Mollie, 'idExterne' => $id])) {
            return new Response(null, Response::HTTP_OK);
        }

        $evenement = new EvenementWebhook(PrestatairePaiement::Mollie, $id, ['id' => $id]);
        $this->entityManager->persist($evenement);
        $this->entityManager->flush();
        $this->bus->dispatch(new TraiterNotificationPaiement((int) $evenement->getId()));

        return new Response(null, Response::HTTP_OK);
    }
}
