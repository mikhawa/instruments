<?php

declare(strict_types=1);

namespace App\State\Panier;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Panier\AjoutLigne;
use App\ApiResource\Panier\PanierCalcule;
use App\Entity\LignePanier;
use App\Service\Panier\CalculateurPanier;
use App\Service\Panier\PanierCourant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/panier/lignes : ajoute un instrument (ou augmente sa quantité),
 * dans la limite du stock disponible, d'une pièce unique et de LignePanier::QUANTITE_MAX.
 * Rien n'est réservé : la réservation a lieu au passage en commande.
 *
 * @implements ProcessorInterface<AjoutLigne, PanierCalcule>
 */
final readonly class AjoutLigneProcessor implements ProcessorInterface
{
    public function __construct(
        private PanierCourant $panierCourant,
        private CalculateurPanier $calculateur,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.ajout_panier')]
        private RateLimiterFactoryInterface $ajoutPanierLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PanierCalcule
    {
        \assert($data instanceof AjoutLigne && null !== $data->instrument);

        $limite = $this->ajoutPanierLimiter->create($this->requestStack->getCurrentRequest()?->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException($limite->getRetryAfter()->getTimestamp() - time(), 'Trop d\'ajouts au panier, réessayez dans un instant.');
        }

        $instrument = $data->instrument;
        $quantiteMax = $instrument->isPublished() ? LignePanier::quantiteMaximale($instrument) : 0;
        if (0 === $quantiteMax) {
            throw new UnprocessableEntityHttpException('Cet instrument n\'est plus disponible.');
        }

        $panier = $this->panierCourant->obtenirOuCreer();
        $ligne = $panier->getLignePour($instrument);
        if (null !== $ligne) {
            $ligne->setQuantite(min($ligne->getQuantite() + $data->quantite, $quantiteMax));
            $panier->actualiserHorodatage();
        } else {
            $this->entityManager->persist(new LignePanier($panier, $instrument, min($data->quantite, $quantiteMax)));
        }
        $this->entityManager->flush();

        return $this->calculateur->calculer($panier);
    }
}
