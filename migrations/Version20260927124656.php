<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927124656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des rencontres avec leur organisateur';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rencontre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(120) NOT NULL, ville VARCHAR(100) NOT NULL, lieu VARCHAR(255) NOT NULL, date_heure DATETIME NOT NULL, places_recherchees INT NOT NULL, description LONGTEXT DEFAULT NULL, organisateur_id INT NOT NULL, INDEX IDX_460C35EDD936B2FA (organisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rencontre ADD CONSTRAINT FK_460C35EDD936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rencontre DROP FOREIGN KEY FK_460C35EDD936B2FA');
        $this->addSql('DROP TABLE rencontre');
    }
}
