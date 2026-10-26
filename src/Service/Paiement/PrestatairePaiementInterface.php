<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;

/**
 * Adaptateur d'un prestataire de paiement en ligne : le code métier ne dépend d'aucun SDK.
 * Les erreurs de communication lèvent PrestataireIndisponibleException.
 */
interface PrestatairePaiementInterface
{
    public function code(): PrestatairePaiement;

    /**
     * Enregistre le paiement chez le prestataire et renvoie la page où envoyer le client.
     *
     * @param string|null $urlNotification NULL quand le prestataire ne peut pas joindre le site (développement local)
     */
    public function creer(Paiement $paiement, string $urlRetour, ?string $urlNotification): PaiementCree;

    /**
     * Statut actuel lu chez le prestataire : seule source de vérité.
     */
    public function etat(Paiement $paiement): EtatPaiement;

    /**
     * Demande le remboursement ; renvoie l'identifiant du remboursement chez le prestataire
     * et s'il est déjà effectif.
     *
     * @return array{0: string, 1: bool}
     */
    public function rembourser(Remboursement $remboursement): array;
}
