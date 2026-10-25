<?php

declare(strict_types=1);

namespace App\ApiResource\Livraison;

/**
 * Mode de livraison proposé pour le panier courant et le pays choisi, avec son prix.
 */
final readonly class OptionLivraison
{
    public function __construct(
        /** À renvoyer dans « modeLivraison » à la création de la commande */
        public string $code,
        public string $nom,
        public ?string $description,
        /** Centimes TTC pour l'envoi entier */
        public int $prixTtc,
        public ?int $delaiJoursMin,
        public ?int $delaiJoursMax,
        /** Retrait sur place : pas d'adresse de livraison */
        public bool $retrait,
    ) {
    }
}
