<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Intl\Countries;
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

    /**
     * Code pays ISO → nom dans la langue demandée (« BE » → « Belgique »).
     */
    #[AsTwigFilter('pays')]
    public function pays(?string $code, string $locale = 'fr'): string
    {
        if (null === $code || '' === $code) {
            return '';
        }

        return Countries::exists($code) ? Countries::getName($code, $locale) : $code;
    }
}
