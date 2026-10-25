<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gabarit d'expédition d'un instrument emballé ; la grille tarifaire est définie par classe.
 * Une commande prend la classe la plus grande de ses instruments.
 */
enum ClasseLivraison: string implements TranslatableInterface
{
    /** Accessoires, petits vents (flûte, harmonica) */
    case PetitColis = 'petit_colis';
    /** Cas courant : oud, saz, violon en étui */
    case Colis = 'colis';
    /** Volumineux mais transportable par messagerie : vielle en caisse, grand tambour */
    case HorsNorme = 'hors_norme';
    /** Harpe, contrebasse : transporteur spécialisé, sur devis (pas de vente en ligne livrée) */
    case TransportDedie = 'transport_dedie';
    /** Remise en main propre à l'atelier uniquement */
    case RetraitUniquement = 'retrait_uniquement';

    /**
     * Rang de taille pour les classes expédiables (null : pas d'expédition par la grille).
     */
    public function rang(): ?int
    {
        return match ($this) {
            self::PetitColis => 1,
            self::Colis => 2,
            self::HorsNorme => 3,
            self::TransportDedie, self::RetraitUniquement => null,
        };
    }

    /**
     * Classes pour lesquelles un tarif peut être saisi dans la grille.
     *
     * @return list<self>
     */
    public static function expediables(): array
    {
        return [self::PetitColis, self::Colis, self::HorsNorme];
    }

    /**
     * Libellé lisible (domaine de traduction « enums »).
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('classe_livraison.'.$this->value, domain: 'enums', locale: $locale);
    }
}
