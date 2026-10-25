<?php

declare(strict_types=1);

namespace App\ApiResource\Commande;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Commande;
use App\State\Commande\CommandeProvider;
use App\State\Commande\CreationCommandeProcessor;
use App\State\Commande\MesCommandesProvider;

/**
 * Commande vue par son client.
 *
 *  - POST /api/commandes : crée la commande à partir du panier courant et réserve le stock ;
 *  - GET /api/commandes : commandes du client connecté ;
 *  - GET /api/commandes/{numero}?jeton=… : détail pour le client connecté, ou pour un invité
 *    muni du jeton secret du lien de suivi (sinon 404).
 */
#[ApiResource(
    shortName: 'Commande',
    operations: [
        new Post(
            uriTemplate: '/commandes',
            input: NouvelleCommande::class,
            read: false,
            processor: CreationCommandeProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/commandes',
            security: "is_granted('IS_AUTHENTICATED')",
            provider: MesCommandesProvider::class,
        ),
        new Get(
            uriTemplate: '/commandes/{numero}',
            requirements: ['numero' => 'CMD-\d{4}-\d{6}'],
            provider: CommandeProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final readonly class CommandeVue
{
    /**
     * @param list<LigneCommandeVue>     $lignes
     * @param array<string, string|null> $adresseLivraison
     * @param array<string, string|null> $adresseFacturation
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $numero,
        /** Valeur de StatutCommande */
        public string $statut,
        public \DateTimeImmutable $createdAt,
        /** Fin de la réservation tant que la commande n'est pas payée */
        public ?\DateTimeImmutable $expireAt,
        public string $email,
        public bool $invite,
        public array $lignes,
        public int $totalHt,
        public int $totalTva,
        public int $fraisPortTtc,
        public int $totalTtc,
        public ?string $libelleLivraison,
        public array $adresseLivraison,
        public array $adresseFacturation,
        public ?string $numeroSuivi,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $shippedAt,
        public ?\DateTimeImmutable $deliveredAt,
        public ?\DateTimeImmutable $cancelledAt,
        /** Jeton du lien de suivi : renvoyé uniquement à la création (page de confirmation) */
        public ?string $jetonSuivi = null,
    ) {
    }

    public static function depuis(Commande $commande, bool $avecJeton = false): self
    {
        return new self(
            numero: (string) $commande->getNumero(),
            statut: $commande->getStatut()->value,
            createdAt: $commande->getCreatedAt(),
            expireAt: $commande->getExpireAt(),
            email: (string) $commande->getEmail(),
            invite: $commande->isInvite(),
            lignes: array_values(array_map(static fn ($ligne): LigneCommandeVue => new LigneCommandeVue(
                reference: $ligne->getReference(),
                libelle: $ligne->getLibelle(),
                quantite: $ligne->getQuantite(),
                prixUnitaireTtc: $ligne->getPrixUnitaireTtc(),
                totalTtc: $ligne->getTotalTtc(),
                instrumentId: $ligne->getInstrument()?->getId(),
            ), $commande->getLignes()->toArray())),
            totalHt: $commande->getTotalHt(),
            totalTva: $commande->getTotalTva(),
            fraisPortTtc: $commande->getFraisPortTtc(),
            totalTtc: $commande->getTotalTtc(),
            libelleLivraison: $commande->getLibelleLivraison(),
            adresseLivraison: $commande->getAdresseLivraison(),
            adresseFacturation: $commande->getAdresseFacturation(),
            numeroSuivi: $commande->getNumeroSuivi(),
            paidAt: $commande->getPaidAt(),
            shippedAt: $commande->getShippedAt(),
            deliveredAt: $commande->getDeliveredAt(),
            cancelledAt: $commande->getCancelledAt(),
            jetonSuivi: $avecJeton ? $commande->getJetonSuivi()->toRfc4122() : null,
        );
    }
}
