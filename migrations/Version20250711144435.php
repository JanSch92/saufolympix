<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250711144435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gamechanger_throw (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, player_id INT NOT NULL, thrown_points INT NOT NULL, player_order INT NOT NULL, points_scored INT NOT NULL, scoring_reason LONGTEXT DEFAULT NULL, thrown_at DATETIME NOT NULL, is_processed TINYINT(1) NOT NULL, INDEX IDX_D77B4EA0E48FD905 (game_id), INDEX IDX_D77B4EA099E6F5DF (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gamechanger_throw ADD CONSTRAINT FK_D77B4EA0E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE gamechanger_throw ADD CONSTRAINT FK_D77B4EA099E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE game CHANGE points_distribution points_distribution JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE split_or_steal_match CHANGE player1_choice player1_choice VARCHAR(20) DEFAULT NULL, CHANGE player2_choice player2_choice VARCHAR(20) DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tournament CHANGE bracket_data bracket_data JSON NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gamechanger_throw DROP FOREIGN KEY FK_D77B4EA0E48FD905');
        $this->addSql('ALTER TABLE gamechanger_throw DROP FOREIGN KEY FK_D77B4EA099E6F5DF');
        $this->addSql('DROP TABLE gamechanger_throw');
        $this->addSql('ALTER TABLE game CHANGE points_distribution points_distribution LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE split_or_steal_match CHANGE player1_choice player1_choice VARCHAR(20) DEFAULT \'NULL\', CHANGE player2_choice player2_choice VARCHAR(20) DEFAULT \'NULL\', CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tournament CHANGE bracket_data bracket_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
