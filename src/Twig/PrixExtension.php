<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Filtre {{ montant|prix }} : centimes → « 1 076,90 € » (même format que le site React).
 */
final class PrixExtension
{
    #[AsTwigFilter('prix')]
    public function prix(int $centimes, string $locale = 'fr'): string
    {
        return (string) (new \NumberFormatter($locale, \NumberFormatter::CURRENCY))->formatCurrency($centimes / 100, 'EUR');
    }
}
