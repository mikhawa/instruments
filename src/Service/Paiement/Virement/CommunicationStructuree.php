<?php

declare(strict_types=1);

namespace App\Service\Paiement\Virement;

/**
 * Communication structurée belge (« OGM-VCS ») : +++123/4567/89002+++.
 * 10 chiffres de référence suivis de 2 chiffres de contrôle (référence modulo 97, 97 si le reste est 0) ;
 * les banques belges refusent une communication dont le contrôle est faux.
 */
final class CommunicationStructuree
{
    public static function depuis(int $reference): string
    {
        if ($reference <= 0 || $reference > 9_999_999_999) {
            throw new \InvalidArgumentException('Référence hors limites pour une communication structurée.');
        }
        $base = str_pad((string) $reference, 10, '0', \STR_PAD_LEFT);
        $chiffres = $base.str_pad((string) (($reference % 97) ?: 97), 2, '0', \STR_PAD_LEFT);

        return \sprintf('+++%s/%s/%s+++', substr($chiffres, 0, 3), substr($chiffres, 3, 4), substr($chiffres, 7, 5));
    }

    public static function estValide(string $communication): bool
    {
        if (1 !== preg_match('#^\+\+\+(\d{3})/(\d{4})/(\d{5})\+\+\+$#', $communication, $morceaux)) {
            return false;
        }
        $chiffres = $morceaux[1].$morceaux[2].$morceaux[3];
        $reference = (int) substr($chiffres, 0, 10);

        return $reference > 0 && (($reference % 97) ?: 97) === (int) substr($chiffres, 10, 2);
    }
}
