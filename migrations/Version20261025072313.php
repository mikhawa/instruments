<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2 : tunnel de commande.
 *  - commande invité (utilisateur_id NULL, email), lien de suivi, expiration de la réservation,
 *    CGV acceptées, mode de livraison, panier d'origine, clé d'idempotence ;
 *  - prix TTC figé sur ligne_commande (même règle que le panier) ;
 *  - grille de livraison zone × mode × classe, avec des valeurs de départ à ajuster dans l'admin.
 */
final class Version20261025072313 extends AbstractMigration
{
    /** Zones de départ (D11 : Belgique + Union européenne) */
    private const ZONES = [
        'BE' => ['Belgique', ['BE']],
        'UE1' => ['Pays voisins', ['DE', 'FR', 'LU', 'NL']],
        'UE2' => ['Reste de l\'Union européenne', ['AT', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LV', 'MT', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK']],
    ];

    /** zone => classe => [prix TTC en centimes, délai min, délai max] (indicatifs) */
    private const TARIFS_DOMICILE = [
        'BE' => ['petit_colis' => [690, 1, 3], 'colis' => [990, 1, 3], 'hors_norme' => [3500, 2, 5]],
        'UE1' => ['petit_colis' => [1490, 2, 5], 'colis' => [2290, 2, 5], 'hors_norme' => [6500, 3, 8]],
        'UE2' => ['petit_colis' => [1990, 3, 8], 'colis' => [3290, 3, 8], 'hors_norme' => [8900, 5, 12]],
    ];

    public function getDescription(): string
    {
        return 'Tunnel de commande : commande invité, expiration, CGV, livraison (grille zone × mode × classe), prix TTC des lignes';
    }

    public function up(Schema $schema): void
    {
        // Grille de livraison
        $this->addSql('CREATE TABLE zone_livraison (id INT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, pays JSON NOT NULL, UNIQUE INDEX UNIQ_289ACC6A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mode_livraison (id INT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(30) NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_retrait TINYINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_C1D2B2A777153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarif_livraison (id INT UNSIGNED AUTO_INCREMENT NOT NULL, classe VARCHAR(20) NOT NULL, prix_ttc INT UNSIGNED NOT NULL, delai_jours_min SMALLINT UNSIGNED DEFAULT NULL, delai_jours_max SMALLINT UNSIGNED DEFAULT NULL, zone_id INT UNSIGNED NOT NULL, mode_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_tarif_livraison (zone_id, mode_id, classe), INDEX IDX_9509472E9F2C3FAB (zone_id), INDEX IDX_9509472E77E5854A (mode_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tarif_livraison ADD CONSTRAINT FK_9509472E9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone_livraison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tarif_livraison ADD CONSTRAINT FK_9509472E77E5854A FOREIGN KEY (mode_id) REFERENCES mode_livraison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE instrument ADD classe_livraison VARCHAR(20) DEFAULT \'colis\' NOT NULL');

        // Commande : colonnes ajoutées NULL, remplies pour les commandes existantes, puis NOT NULL
        $this->addSql('ALTER TABLE commande ADD email VARCHAR(180) DEFAULT NULL, ADD telephone VARCHAR(30) DEFAULT NULL, ADD jeton_suivi BINARY(16) DEFAULT NULL, ADD expire_at DATETIME DEFAULT NULL, ADD cgv_version VARCHAR(30) DEFAULT NULL, ADD cgv_acceptees_at DATETIME DEFAULT NULL, ADD canal VARCHAR(20) DEFAULT \'site\' NOT NULL, ADD libelle_livraison VARCHAR(100) DEFAULT NULL, ADD cle_idempotence VARCHAR(64) DEFAULT NULL, ADD mode_livraison_id INT UNSIGNED DEFAULT NULL, ADD panier_id INT UNSIGNED DEFAULT NULL, CHANGE utilisateur_id utilisateur_id INT UNSIGNED DEFAULT NULL');
        $this->addSql('UPDATE commande c JOIN utilisateur u ON u.id = c.utilisateur_id SET c.email = u.email');
        $this->addSql('UPDATE commande SET jeton_suivi = RANDOM_BYTES(16)');
        $this->addSql('ALTER TABLE commande MODIFY email VARCHAR(180) NOT NULL, MODIFY jeton_suivi BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D458F1D6 FOREIGN KEY (mode_livraison_id) REFERENCES mode_livraison (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DF77D927C FOREIGN KEY (panier_id) REFERENCES panier (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D847920A2 ON commande (jeton_suivi)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DF525847C ON commande (cle_idempotence)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D458F1D6 ON commande (mode_livraison_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67DF77D927C ON commande (panier_id)');
        $this->addSql('CREATE INDEX idx_commande_statut_expiration ON commande (statut, expire_at)');

        // Ligne de commande : prix TTC unitaire recalculé avec la règle du panier
        $this->addSql('ALTER TABLE ligne_commande ADD prix_unitaire_ttc INT UNSIGNED DEFAULT NULL, ADD total_ttc INT UNSIGNED DEFAULT NULL');
        $this->addSql('UPDATE ligne_commande SET prix_unitaire_ttc = ROUND(prix_unitaire_ht * (10000 + taux_tva) / 10000), total_ttc = ROUND(prix_unitaire_ht * (10000 + taux_tva) / 10000) * quantite');
        $this->addSql('ALTER TABLE ligne_commande MODIFY prix_unitaire_ttc INT UNSIGNED NOT NULL, MODIFY total_ttc INT UNSIGNED NOT NULL');
        $this->addSql('UPDATE commande c JOIN (SELECT commande_id, SUM(total_ttc) - SUM(total_ht) AS tva, SUM(total_ttc) AS ttc FROM ligne_commande GROUP BY commande_id) l ON l.commande_id = c.id SET c.total_tva = l.tva, c.total_ttc = l.ttc + c.frais_port_ttc');

        // Grille de départ (indicative, modifiable dans l'admin)
        $this->addSql('INSERT INTO mode_livraison (code, nom, description, is_retrait, is_active, position) VALUES (\'domicile\', \'Livraison à domicile\', \'Colis suivi, remis contre signature, emballage renforcé.\', 0, 1, 1), (\'retrait_atelier\', \'Retrait à l\'\'atelier\', \'Gratuit, sur rendez-vous : nous vous contactons dès que l\'\'instrument est prêt.\', 1, 1, 2)');
        foreach (self::ZONES as $code => [$nom, $pays]) {
            $this->addSql('INSERT INTO zone_livraison (code, nom, pays) VALUES (?, ?, ?)', [$code, $nom, json_encode($pays)]);
        }
        foreach (self::TARIFS_DOMICILE as $zone => $tarifs) {
            foreach ($tarifs as $classe => [$prix, $min, $max]) {
                $this->addSql(
                    'INSERT INTO tarif_livraison (zone_id, mode_id, classe, prix_ttc, delai_jours_min, delai_jours_max) SELECT z.id, m.id, ?, ?, ?, ? FROM zone_livraison z, mode_livraison m WHERE z.code = ? AND m.code = \'domicile\'',
                    [$classe, $prix, $min, $max, $zone],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_livraison DROP FOREIGN KEY FK_9509472E9F2C3FAB');
        $this->addSql('ALTER TABLE tarif_livraison DROP FOREIGN KEY FK_9509472E77E5854A');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D458F1D6');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DF77D927C');
        $this->addSql('DROP TABLE mode_livraison');
        $this->addSql('DROP TABLE tarif_livraison');
        $this->addSql('DROP TABLE zone_livraison');
        $this->addSql('DROP INDEX UNIQ_6EEAA67D847920A2 ON commande');
        $this->addSql('DROP INDEX UNIQ_6EEAA67DF525847C ON commande');
        $this->addSql('DROP INDEX IDX_6EEAA67D458F1D6 ON commande');
        $this->addSql('DROP INDEX IDX_6EEAA67DF77D927C ON commande');
        $this->addSql('DROP INDEX idx_commande_statut_expiration ON commande');
        // Échoue s'il existe des commandes invité (utilisateur_id NULL) : à traiter avant tout retour arrière
        $this->addSql('ALTER TABLE commande DROP email, DROP telephone, DROP jeton_suivi, DROP expire_at, DROP cgv_version, DROP cgv_acceptees_at, DROP canal, DROP libelle_livraison, DROP cle_idempotence, DROP mode_livraison_id, DROP panier_id, CHANGE utilisateur_id utilisateur_id INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE instrument DROP classe_livraison');
        $this->addSql('ALTER TABLE ligne_commande DROP prix_unitaire_ttc, DROP total_ttc');
    }
}
