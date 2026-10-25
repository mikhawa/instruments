<?php

declare(strict_types=1);

namespace App\Service\Commande;

/**
 * Commande impossible. « Conflit » : le panier a changé (prix, stock) depuis son affichage,
 * le client doit le revoir (HTTP 409) ; sinon la demande est invalide (HTTP 422).
 */
final class CommandeRefuseeException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $conflit = false)
    {
        parent::__construct($message);
    }
}
