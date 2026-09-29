<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929070057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : comptes, catalogue multilingue, stocks et commandes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adresse (id INT UNSIGNED AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) DEFAULT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, societe VARCHAR(150) DEFAULT NULL, rue VARCHAR(255) NOT NULL, complement VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(20) NOT NULL, ville VARCHAR(100) NOT NULL, pays CHAR(2) NOT NULL, is_default TINYINT DEFAULT 0 NOT NULL, utilisateur_id INT UNSIGNED NOT NULL, INDEX IDX_C35F0816FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie (id INT UNSIGNED AUTO_INCREMENT NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, parent_id INT UNSIGNED DEFAULT NULL, INDEX IDX_497DD634727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie_traduction (id INT UNSIGNED AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, nom VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, categorie_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_categorie_traduction_locale (categorie_id, locale), UNIQUE INDEX uniq_categorie_traduction_slug (locale, slug), INDEX IDX_5145C904BCF5E72D (categorie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande (id INT UNSIGNED AUTO_INCREMENT NOT NULL, numero VARCHAR(20) NOT NULL, statut VARCHAR(30) NOT NULL, adresse_livraison JSON NOT NULL, adresse_facturation JSON NOT NULL, total_ht INT UNSIGNED DEFAULT 0 NOT NULL, total_tva INT UNSIGNED DEFAULT 0 NOT NULL, frais_port_ttc INT UNSIGNED DEFAULT 0 NOT NULL, total_ttc INT UNSIGNED DEFAULT 0 NOT NULL, locale VARCHAR(5) NOT NULL, commentaire_client LONGTEXT DEFAULT NULL, numero_suivi VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, utilisateur_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_6EEAA67DF55AE19E (numero), INDEX idx_commande_utilisateur_date (utilisateur_id, created_at), INDEX idx_commande_statut_date (statut, created_at), INDEX IDX_6EEAA67DFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE instrument (id INT UNSIGNED AUTO_INCREMENT NOT NULL, reference VARCHAR(30) NOT NULL, pays_origine CHAR(2) DEFAULT NULL, region_origine VARCHAR(100) DEFAULT NULL, facteur VARCHAR(150) DEFAULT NULL, annee_fabrication SMALLINT DEFAULT NULL, etat VARCHAR(20) NOT NULL, is_piece_unique TINYINT DEFAULT 0 NOT NULL, prix_ht INT UNSIGNED NOT NULL, taux_tva SMALLINT UNSIGNED DEFAULT 2100 NOT NULL, poids_grammes INT UNSIGNED DEFAULT NULL, dimensions VARCHAR(100) DEFAULT NULL, is_published TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, categorie_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_3CBF69DDAEA34913 (reference), INDEX idx_instrument_publication (is_published, categorie_id), INDEX idx_instrument_pays (pays_origine), INDEX IDX_3CBF69DDBCF5E72D (categorie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE instrument_image (id INT UNSIGNED AUTO_INCREMENT NOT NULL, fichier VARCHAR(255) NOT NULL, alt VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT 0 NOT NULL, instrument_id INT UNSIGNED NOT NULL, INDEX IDX_1DE7B036CF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE instrument_traduction (id INT UNSIGNED AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, nom VARCHAR(150) NOT NULL, slug VARCHAR(170) NOT NULL, description_courte VARCHAR(300) DEFAULT NULL, description LONGTEXT DEFAULT NULL, materiaux VARCHAR(255) DEFAULT NULL, histoire LONGTEXT DEFAULT NULL, instrument_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_instrument_traduction_locale (instrument_id, locale), UNIQUE INDEX uniq_instrument_traduction_slug (locale, slug), INDEX IDX_4130A8CF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ligne_commande (id INT UNSIGNED AUTO_INCREMENT NOT NULL, reference VARCHAR(30) NOT NULL, libelle VARCHAR(150) NOT NULL, prix_unitaire_ht INT UNSIGNED NOT NULL, taux_tva SMALLINT UNSIGNED NOT NULL, quantite SMALLINT UNSIGNED NOT NULL, total_ht INT UNSIGNED NOT NULL, commande_id INT UNSIGNED NOT NULL, instrument_id INT UNSIGNED DEFAULT NULL, INDEX IDX_3170B74B82EA2E54 (commande_id), INDEX IDX_3170B74BCF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mouvement_stock (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, quantite INT NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, instrument_id INT UNSIGNED NOT NULL, commande_id INT UNSIGNED DEFAULT NULL, utilisateur_id INT UNSIGNED DEFAULT NULL, INDEX idx_mouvement_stock_instrument_date (instrument_id, created_at), INDEX IDX_61E2C8EBCF11D9C (instrument_id), INDEX IDX_61E2C8EB82EA2E54 (commande_id), INDEX IDX_61E2C8EBFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock (id INT UNSIGNED AUTO_INCREMENT NOT NULL, quantite INT DEFAULT 0 NOT NULL, quantite_reservee INT DEFAULT 0 NOT NULL, seuil_alerte INT DEFAULT 1 NOT NULL, emplacement VARCHAR(50) DEFAULT NULL, version INT DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, instrument_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_4B365660CF11D9C (instrument_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT UNSIGNED AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        // Contraintes CHECK (non générées par Doctrine) : invariants de stock et de commande
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT chk_stock_quantite CHECK (quantite >= 0)');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT chk_stock_reservee CHECK (quantite_reservee >= 0 AND quantite_reservee <= quantite)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT chk_ligne_commande_quantite CHECK (quantite > 0)');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT FK_C35F0816FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE categorie ADD CONSTRAINT FK_497DD634727ACA70 FOREIGN KEY (parent_id) REFERENCES categorie (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE categorie_traduction ADD CONSTRAINT FK_5145C904BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE instrument ADD CONSTRAINT FK_3CBF69DDBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE instrument_image ADD CONSTRAINT FK_1DE7B036CF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE instrument_traduction ADD CONSTRAINT FK_4130A8CF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BCF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EBCF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EB82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EBFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660CF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adresse DROP FOREIGN KEY FK_C35F0816FB88E14F');
        $this->addSql('ALTER TABLE categorie DROP FOREIGN KEY FK_497DD634727ACA70');
        $this->addSql('ALTER TABLE categorie_traduction DROP FOREIGN KEY FK_5145C904BCF5E72D');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DFB88E14F');
        $this->addSql('ALTER TABLE instrument DROP FOREIGN KEY FK_3CBF69DDBCF5E72D');
        $this->addSql('ALTER TABLE instrument_image DROP FOREIGN KEY FK_1DE7B036CF11D9C');
        $this->addSql('ALTER TABLE instrument_traduction DROP FOREIGN KEY FK_4130A8CF11D9C');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BCF11D9C');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EBCF11D9C');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EB82EA2E54');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EBFB88E14F');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660CF11D9C');
        $this->addSql('DROP TABLE adresse');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE categorie_traduction');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE instrument');
        $this->addSql('DROP TABLE instrument_image');
        $this->addSql('DROP TABLE instrument_traduction');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('DROP TABLE mouvement_stock');
        $this->addSql('DROP TABLE stock');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
