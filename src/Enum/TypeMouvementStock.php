<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'une variation de stock (journal mouvement_stock).
 */
enum TypeMouvementStock: string
{
    /** Réception de marchandise */
    case Entree = 'entree';
    /** Blocage de quantité pour une commande non expédiée */
    case Reservation = 'reservation';
    /** Annulation d'une réservation */
    case Liberation = 'liberation';
    /** Expédition d'une commande */
    case Sortie = 'sortie';
    /** Correction d'inventaire, casse, perte */
    case Ajustement = 'ajustement';
    /** Retour client remis en stock */
    case Retour = 'retour';
}
