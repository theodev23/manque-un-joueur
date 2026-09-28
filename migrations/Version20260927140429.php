<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927140429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des participations avec unicité du couple utilisateur-rencontre';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participation (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) NOT NULL, date_demande DATETIME NOT NULL, utilisateur_id INT NOT NULL, rencontre_id INT NOT NULL, UNIQUE INDEX UNIQ_PARTICIPATION_UTILISATEUR_RENCONTRE (utilisateur_id, rencontre_id), INDEX IDX_AB55E24FFB88E14F (utilisateur_id), INDEX IDX_AB55E24F6CFC0818 (rencontre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F6CFC0818 FOREIGN KEY (rencontre_id) REFERENCES rencontre (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FFB88E14F');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F6CFC0818');
        $this->addSql('DROP TABLE participation');
    }
}
