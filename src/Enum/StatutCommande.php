<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Étapes du cycle de vie d'une commande.
 * Les transitions autorisées seront portées par Symfony Workflow.
 */
enum StatutCommande: string
{
    case EnAttentePaiement = 'en_attente_paiement';
    case Payee = 'payee';
    case EnPreparation = 'en_preparation';
    case Expediee = 'expediee';
    case Livree = 'livree';
    case Annulee = 'annulee';
    case Retournee = 'retournee';
}
