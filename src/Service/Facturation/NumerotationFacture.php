<?php

declare(strict_types=1);

namespace App\Service\Facturation;

use App\Enum\TypeFacture;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Numéro suivant d'une série, continu et sans trou (obligation légale, prepare/06 § 2).
 *
 * La ligne de sequence_facture est verrouillée (SELECT … FOR UPDATE) jusqu'à la fin de la
 * transaction de l'appelant : deux émissions simultanées sont servies l'une après l'autre,
 * et une émission annulée (rollback) rend son numéro. À appeler dans la transaction qui
 * enregistre la facture ; sans transaction ouverte, une transaction propre est utilisée.
 */
final readonly class NumerotationFacture
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function suivant(TypeFacture $type, \DateTimeImmutable $date): string
    {
        $connexion = $this->entityManager->getConnection();
        $serie = $type->serie();
        $annee = (int) $date->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('Y');

        $propre = !$connexion->isTransactionActive();
        if ($propre) {
            $connexion->beginTransaction();
        }
        try {
            // Crée la ligne de l'année si besoin, sans écraser un compteur existant
            $connexion->executeStatement('INSERT INTO sequence_facture (serie, annee, dernier_numero) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE dernier_numero = dernier_numero', [$serie, $annee]);
            $dernier = (int) $connexion->fetchOne('SELECT dernier_numero FROM sequence_facture WHERE serie = ? AND annee = ? FOR UPDATE', [$serie, $annee]);
            $connexion->executeStatement('UPDATE sequence_facture SET dernier_numero = ? WHERE serie = ? AND annee = ?', [$dernier + 1, $serie, $annee]);
            if ($propre) {
                $connexion->commit();
            }
        } catch (\Throwable $e) {
            if ($propre) {
                $connexion->rollBack();
            }
            throw $e;
        }

        return \sprintf('%s%d-%06d', $serie, $annee, $dernier + 1);
    }
}
