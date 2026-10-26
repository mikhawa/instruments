<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3 : paiement.
 *  - paiement : tentatives de paiement d'une commande (Mollie, simulation, virement SEPA) ;
 *  - remboursement : remboursements décidés puis envoyés au prestataire ;
 *  - evenement_webhook : journal des notifications reçues des prestataires ;
 *  - commande.motif_annulation : expirée, remplacée ou annulée (sort d'un paiement reçu après coup).
 */
final class Version20261026140218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement : tables paiement, remboursement, evenement_webhook ; motif d\'annulation des commandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE evenement_webhook (id INT UNSIGNED AUTO_INCREMENT NOT NULL, prestataire VARCHAR(20) NOT NULL, reference VARCHAR(100) NOT NULL, charge_utile JSON NOT NULL, recu_at DATETIME NOT NULL, traite_at DATETIME DEFAULT NULL, erreur VARCHAR(255) DEFAULT NULL, INDEX idx_evenement_webhook_reference (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paiement (id INT UNSIGNED AUTO_INCREMENT NOT NULL, prestataire VARCHAR(20) NOT NULL, moyen VARCHAR(30) DEFAULT NULL, id_externe VARCHAR(100) DEFAULT NULL, url_paiement VARCHAR(500) DEFAULT NULL, montant INT UNSIGNED NOT NULL, devise CHAR(3) DEFAULT \'EUR\' NOT NULL, montant_rembourse INT UNSIGNED DEFAULT 0 NOT NULL, statut VARCHAR(30) NOT NULL, reference_virement VARCHAR(21) DEFAULT NULL, donnees JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paye_at DATETIME DEFAULT NULL, commande_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_B1DC7A1EEC77B41E (id_externe), UNIQUE INDEX UNIQ_B1DC7A1EF54794D9 (reference_virement), INDEX idx_paiement_statut_date (statut, created_at), INDEX IDX_B1DC7A1E82EA2E54 (commande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE remboursement (id INT UNSIGNED AUTO_INCREMENT NOT NULL, montant INT UNSIGNED NOT NULL, motif VARCHAR(255) NOT NULL, id_externe VARCHAR(100) DEFAULT NULL, statut VARCHAR(20) NOT NULL, erreur VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, effectue_at DATETIME DEFAULT NULL, paiement_id INT UNSIGNED NOT NULL, utilisateur_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_C0C0D9EFEC77B41E (id_externe), INDEX idx_remboursement_statut (statut), INDEX IDX_C0C0D9EF2A4C4478 (paiement_id), INDEX IDX_C0C0D9EFFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT FK_C0C0D9EF2A4C4478 FOREIGN KEY (paiement_id) REFERENCES paiement (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT FK_C0C0D9EFFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commande ADD motif_annulation VARCHAR(20) DEFAULT NULL');

        // Garde-fous en base, en plus des contrôles des entités
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT chk_paiement_montant CHECK (montant > 0 AND montant_rembourse <= montant)');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT chk_remboursement_montant CHECK (montant > 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E82EA2E54');
        $this->addSql('ALTER TABLE remboursement DROP FOREIGN KEY FK_C0C0D9EF2A4C4478');
        $this->addSql('ALTER TABLE remboursement DROP FOREIGN KEY FK_C0C0D9EFFB88E14F');
        $this->addSql('DROP TABLE evenement_webhook');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE remboursement');
        $this->addSql('ALTER TABLE commande DROP motif_annulation');
    }
}
