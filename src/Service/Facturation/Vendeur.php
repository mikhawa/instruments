<?php

declare(strict_types=1);

namespace App\Service\Facturation;

use App\Service\Paiement\Virement\CoordonneesVirement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Identité du vendeur imprimée sur les factures (copiée dans chaque facture à l'émission).
 * Valeurs dans shared/.env.local en préprod et en production.
 */
final readonly class Vendeur
{
    public function __construct(
        #[Autowire(env: 'VENDEUR_NOM')]
        private string $nom,
        /** Lignes de l'adresse séparées par « | » */
        #[Autowire(env: 'VENDEUR_ADRESSE')]
        private string $adresse,
        #[Autowire(env: 'VENDEUR_TVA')]
        private string $numeroTva,
        #[Autowire(env: 'VENDEUR_EMAIL')]
        private string $email,
        private CoordonneesVirement $coordonneesVirement,
    ) {
    }

    public function isConfigure(): bool
    {
        return '' !== trim($this->nom) && '' !== trim($this->adresse) && '' !== trim($this->numeroTva);
    }

    /**
     * @return array{nom: string, adresse: list<string>, tva: string, email: string, iban: string|null, bic: string|null}
     */
    public function enTableau(): array
    {
        return [
            'nom' => trim($this->nom),
            'adresse' => array_values(array_filter(array_map('trim', explode('|', $this->adresse)), static fn (string $ligne): bool => '' !== $ligne)),
            'tva' => trim($this->numeroTva),
            'email' => trim($this->email),
            'iban' => $this->coordonneesVirement->isConfigure() ? $this->coordonneesVirement->getIbanLisible() : null,
            'bic' => '' !== $this->coordonneesVirement->getBic() ? $this->coordonneesVirement->getBic() : null,
        ];
    }
}
