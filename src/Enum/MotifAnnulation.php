<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Pourquoi une commande a été annulée : décide du sort d'un paiement reçu ensuite
 * (seule une commande expirée peut être reprise, voir EncaissementPaiement).
 */
enum MotifAnnulation: string
{
    /** Réservation dépassée sans paiement (transition « expirer ») */
    case Expiree = 'expiree';
    /** Remplacée par une nouvelle commande du même panier */
    case Remplacee = 'remplacee';
    /** Annulée par l'administrateur */
    case Annulee = 'annulee';
}
