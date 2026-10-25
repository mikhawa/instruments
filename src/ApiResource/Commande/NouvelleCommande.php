<?php

declare(strict_types=1);

namespace App\ApiResource\Commande;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/commandes. Les articles viennent du panier courant (cookie ou compte),
 * jamais du corps de la requête. En-tête facultatif « Idempotency-Key » contre le double envoi.
 */
final class NouvelleCommande
{
    /** Obligatoire sans compte ; ignoré pour un client connecté (e-mail du compte) */
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^[0-9 +().-]*$/', message: 'Numéro de téléphone invalide.')]
    public ?string $telephone = null;

    /** Inutile pour un mode de retrait */
    #[Assert\Valid]
    public ?AdresseSaisie $adresseLivraison = null;

    /** NULL : identique à l'adresse de livraison */
    #[Assert\Valid]
    public ?AdresseSaisie $adresseFacturation = null;

    /** Code d'une option de GET /api/panier/livraison */
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $modeLivraison = '';

    #[Assert\Length(max: 2000)]
    public ?string $commentaire = null;

    #[Assert\IsTrue(message: 'Vous devez accepter les conditions générales de vente.')]
    public bool $accepteCgv = false;

    /**
     * Total TTC affiché au client (centimes, frais de port compris). S'il ne correspond plus
     * au calcul du serveur (prix ou stock modifiés entre-temps), la commande est refusée (409).
     */
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $totalTtcAttendu = null;
}
