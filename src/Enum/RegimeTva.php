<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Régime de TVA d'un instrument (prepare/06-fiscalite-et-juridique.md § 1.2).
 *
 * Sous le régime particulier de la marge (biens achetés sans TVA déductible), la TVA ne porte
 * que sur la marge (prix de vente − prix d'achat) : elle n'apparaît pas sur la facture et
 * l'acheteur ne peut pas la déduire ; une mention propre au type de bien est obligatoire.
 */
enum RegimeTva: string implements TranslatableInterface
{
    case Normal = 'normal';
    case MargeOccasion = 'marge_occasion';
    case MargeAntiquite = 'marge_antiquite';
    case MargeArt = 'marge_art';

    public function isMarge(): bool
    {
        return self::Normal !== $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('regime_tva.'.$this->value, domain: 'enums', locale: $locale);
    }
}
