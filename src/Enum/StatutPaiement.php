<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'une tentative de paiement, aligné sur les statuts des prestataires (Mollie).
 */
enum StatutPaiement: string
{
    /** Créé, le client n'a pas encore payé */
    case Ouvert = 'ouvert';
    /** En cours de traitement par la banque (ou virement attendu) */
    case EnAttente = 'en_attente';
    case Autorise = 'autorise';
    case Paye = 'paye';
    case Echoue = 'echoue';
    case Annule = 'annule';
    case Expire = 'expire';
    case Rembourse = 'rembourse';
    case PartiellementRembourse = 'partiellement_rembourse';

    /**
     * Le paiement peut encore aboutir : il faut continuer à suivre son statut.
     */
    public function isEnCours(): bool
    {
        return \in_array($this, [self::Ouvert, self::EnAttente, self::Autorise], true);
    }

    /**
     * L'argent a été reçu (même s'il a été rendu depuis, en tout ou en partie).
     */
    public function isEncaisse(): bool
    {
        return \in_array($this, [self::Paye, self::Rembourse, self::PartiellementRembourse], true);
    }
}
