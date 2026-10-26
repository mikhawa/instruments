<?php

declare(strict_types=1);

namespace App\Service\Paiement;

/**
 * Le prestataire est injoignable ou a refusé la demande (clé invalide, panne…).
 */
final class PrestataireIndisponibleException extends \RuntimeException
{
    /**
     * @param bool $definitif le prestataire a refusé la demande elle-même (la renvoyer ne servirait à rien)
     */
    public function __construct(string $message, public readonly bool $definitif = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
