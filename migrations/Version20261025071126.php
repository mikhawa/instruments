<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 : panier en base (visiteur par jeton de cookie, client par utilisateur_id).
 */
final class Version20261025071126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Panier : tables panier et ligne_panier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ligne_panier (id INT UNSIGNED AUTO_INCREMENT NOT NULL, quantite SMALLINT UNSIGNED NOT NULL, prix_ht_ajout INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, panier_id INT UNSIGNED NOT NULL, instrument_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_ligne_panier_instrument (panier_id, instrument_id), INDEX IDX_21691B4F77D927C (panier_id), INDEX IDX_21691B4CF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE panier (id INT UNSIGNED AUTO_INCREMENT NOT NULL, jeton BINARY(16) NOT NULL, locale VARCHAR(5) NOT NULL, relance_envoyee_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, utilisateur_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_24CC0DF22CF647B (jeton), UNIQUE INDEX UNIQ_24CC0DF2FB88E14F (utilisateur_id), INDEX idx_panier_mise_a_jour (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        // Contrainte CHECK (non générée par Doctrine)
        $this->addSql('ALTER TABLE ligne_panier ADD CONSTRAINT chk_ligne_panier_quantite CHECK (quantite > 0)');
        $this->addSql('ALTER TABLE ligne_panier ADD CONSTRAINT FK_21691B4F77D927C FOREIGN KEY (panier_id) REFERENCES panier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_panier ADD CONSTRAINT FK_21691B4CF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE panier ADD CONSTRAINT FK_24CC0DF2FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_panier DROP FOREIGN KEY FK_21691B4F77D927C');
        $this->addSql('ALTER TABLE ligne_panier DROP FOREIGN KEY FK_21691B4CF11D9C');
        $this->addSql('ALTER TABLE panier DROP FOREIGN KEY FK_24CC0DF2FB88E14F');
        $this->addSql('DROP TABLE ligne_panier');
        $this->addSql('DROP TABLE panier');
    }
}
