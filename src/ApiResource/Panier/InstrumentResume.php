<?php

declare(strict_types=1);

namespace App\ApiResource\Panier;

/**
 * Ce qu'un collectionneur vérifie dans son panier : nom, origine, état, photo.
 * Libellés dans la langue du panier (jamais enregistrés dans le panier).
 */
final readonly class InstrumentResume
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $nom,
        public ?string $slug,
        /** Chemin public de la première photo */
        public ?string $image,
        public string $etat,
        public ?int $anneeFabrication,
        public ?string $paysOrigine,
        public ?string $regionOrigine,
        public bool $pieceUnique,
    ) {
    }
}
