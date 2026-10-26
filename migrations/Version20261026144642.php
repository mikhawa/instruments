<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4 : factures et régime de TVA (hypothèses à valider avec le comptable, voir EmissionFacture).
 *  - facture : factures et notes de crédit figées, numérotées sans trou par série et par année ;
 *  - sequence_facture : dernier numéro par série et par année (verrou à l'émission) ;
 *  - instrument.regime_tva, instrument.prix_achat : régime de la marge ;
 *  - ligne_commande.regime_tva, ligne_commande.tva_marge : copie figée et TVA due sur la marge.
 */
final class Version20261026144642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Factures, notes de crédit et régime de la marge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE facture (id INT UNSIGNED AUTO_INCREMENT NOT NULL, numero VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, date_emission DATETIME NOT NULL, locale VARCHAR(5) NOT NULL, vendeur JSON NOT NULL, client JSON NOT NULL, lignes JSON NOT NULL, ventilation_tva JSON NOT NULL, mentions JSON NOT NULL, total_ht INT UNSIGNED NOT NULL, total_tva INT UNSIGNED NOT NULL, total_ttc INT UNSIGNED NOT NULL, payee_at DATETIME DEFAULT NULL, moyen_paiement VARCHAR(30) DEFAULT NULL, motif VARCHAR(255) DEFAULT NULL, commande_id INT UNSIGNED NOT NULL, facture_origine_id INT UNSIGNED DEFAULT NULL, remboursement_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_FE866410F55AE19E (numero), INDEX idx_facture_date (date_emission), INDEX IDX_FE86641082EA2E54 (commande_id), INDEX IDX_FE866410EA98768A (facture_origine_id), INDEX IDX_FE866410F61EE8B (remboursement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sequence_facture (serie VARCHAR(10) NOT NULL, annee SMALLINT UNSIGNED NOT NULL, dernier_numero INT UNSIGNED DEFAULT 0 NOT NULL, PRIMARY KEY (serie, annee)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410EA98768A FOREIGN KEY (facture_origine_id) REFERENCES facture (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410F61EE8B FOREIGN KEY (remboursement_id) REFERENCES remboursement (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE instrument ADD regime_tva VARCHAR(20) DEFAULT \'normal\' NOT NULL, ADD prix_achat INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_commande ADD regime_tva VARCHAR(20) DEFAULT \'normal\' NOT NULL, ADD tva_marge INT DEFAULT NULL');

        // Garde-fou en base : une facture est toujours cohérente
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT chk_facture_totaux CHECK (total_ht + total_tva = total_ttc)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641082EA2E54');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410EA98768A');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410F61EE8B');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE sequence_facture');
        $this->addSql('ALTER TABLE instrument DROP regime_tva, DROP prix_achat');
        $this->addSql('ALTER TABLE ligne_commande DROP regime_tva, DROP tva_marge');
    }
}
