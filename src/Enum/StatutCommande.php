<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Étapes du cycle de vie d'une commande.
 * Les transitions autorisées seront portées par Symfony Workflow.
 */
enum StatutCommande: string implements TranslatableInterface
{
    case EnAttentePaiement = 'en_attente_paiement';
    case Payee = 'payee';
    case EnPreparation = 'en_preparation';
    case Expediee = 'expediee';
    case Livree = 'livree';
    case Annulee = 'annulee';
    case Retournee = 'retournee';

    /**
     * Libellé lisible (domaine de traduction « enums »).
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('statut_commande.'.$this->value, domain: 'enums', locale: $locale);
    }
}
