<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * État physique d'un instrument proposé à la vente.
 */
enum EtatInstrument: string implements TranslatableInterface
{
    case Neuf = 'neuf';
    case Occasion = 'occasion';
    case Ancien = 'ancien';
    case Restaure = 'restaure';

    /**
     * Libellé lisible (domaine de traduction « enums »).
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('etat_instrument.'.$this->value, domain: 'enums', locale: $locale);
    }
}
