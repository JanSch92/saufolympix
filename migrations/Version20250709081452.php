<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709081452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, olympix_id INT NOT NULL, name VARCHAR(255) NOT NULL, game_type VARCHAR(50) NOT NULL, team_size INT DEFAULT NULL, points_distribution JSON DEFAULT NULL, status VARCHAR(50) NOT NULL, order_position INT NOT NULL, INDEX IDX_232B318C88C46E09 (olympix_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_result (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, player_id INT NOT NULL, points INT NOT NULL, position INT NOT NULL, team_id INT DEFAULT NULL, joker_double_applied TINYINT(1) NOT NULL, INDEX IDX_6E5F6CDBE48FD905 (game_id), INDEX IDX_6E5F6CDB99E6F5DF (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE joker (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, game_id INT NOT NULL, target_player_id INT DEFAULT NULL, joker_type VARCHAR(50) NOT NULL, is_used TINYINT(1) NOT NULL, used_at DATETIME NOT NULL, INDEX IDX_94E6D49799E6F5DF (player_id), INDEX IDX_94E6D497E48FD905 (game_id), INDEX IDX_94E6D497AD5287F3 (target_player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE olympix (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, olympix_id INT NOT NULL, name VARCHAR(255) NOT NULL, total_points INT NOT NULL, joker_double_used TINYINT(1) NOT NULL, joker_swap_used TINYINT(1) NOT NULL, INDEX IDX_98197A6588C46E09 (olympix_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_answer (id INT AUTO_INCREMENT NOT NULL, quiz_question_id INT NOT NULL, player_id INT NOT NULL, answer NUMERIC(10, 2) NOT NULL, points_earned INT NOT NULL, answered_at DATETIME NOT NULL, INDEX IDX_3799BA7C3101E51F (quiz_question_id), INDEX IDX_3799BA7C99E6F5DF (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_question (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, question LONGTEXT NOT NULL, correct_answer NUMERIC(10, 2) NOT NULL, order_position INT NOT NULL, INDEX IDX_6033B00BE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE split_or_steal_match (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, player1_id INT NOT NULL, player2_id INT NOT NULL, points_at_stake INT DEFAULT NULL, player1_choice VARCHAR(20) DEFAULT NULL, player2_choice VARCHAR(20) DEFAULT NULL, player1_points INT DEFAULT NULL, player2_points INT DEFAULT NULL, is_completed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FE397AE9E48FD905 (game_id), INDEX IDX_FE397AE9C0990423 (player1_id), INDEX IDX_FE397AE9D22CABCD (player2_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, bracket_data JSON NOT NULL, current_round INT NOT NULL, is_completed TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_BD5FB8D9E48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C88C46E09 FOREIGN KEY (olympix_id) REFERENCES olympix (id)');
        $this->addSql('ALTER TABLE game_result ADD CONSTRAINT FK_6E5F6CDBE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE game_result ADD CONSTRAINT FK_6E5F6CDB99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_94E6D49799E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_94E6D497E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_94E6D497AD5287F3 FOREIGN KEY (target_player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A6588C46E09 FOREIGN KEY (olympix_id) REFERENCES olympix (id)');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_3799BA7C3101E51F FOREIGN KEY (quiz_question_id) REFERENCES quiz_question (id)');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_3799BA7C99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_6033B00BE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE split_or_steal_match ADD CONSTRAINT FK_FE397AE9E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE split_or_steal_match ADD CONSTRAINT FK_FE397AE9C0990423 FOREIGN KEY (player1_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE split_or_steal_match ADD CONSTRAINT FK_FE397AE9D22CABCD FOREIGN KEY (player2_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D9E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318C88C46E09');
        $this->addSql('ALTER TABLE game_result DROP FOREIGN KEY FK_6E5F6CDBE48FD905');
        $this->addSql('ALTER TABLE game_result DROP FOREIGN KEY FK_6E5F6CDB99E6F5DF');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_94E6D49799E6F5DF');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_94E6D497E48FD905');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_94E6D497AD5287F3');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A6588C46E09');
        $this->addSql('ALTER TABLE quiz_answer DROP FOREIGN KEY FK_3799BA7C3101E51F');
        $this->addSql('ALTER TABLE quiz_answer DROP FOREIGN KEY FK_3799BA7C99E6F5DF');
        $this->addSql('ALTER TABLE quiz_question DROP FOREIGN KEY FK_6033B00BE48FD905');
        $this->addSql('ALTER TABLE split_or_steal_match DROP FOREIGN KEY FK_FE397AE9E48FD905');
        $this->addSql('ALTER TABLE split_or_steal_match DROP FOREIGN KEY FK_FE397AE9C0990423');
        $this->addSql('ALTER TABLE split_or_steal_match DROP FOREIGN KEY FK_FE397AE9D22CABCD');
        $this->addSql('ALTER TABLE tournament DROP FOREIGN KEY FK_BD5FB8D9E48FD905');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE game_result');
        $this->addSql('DROP TABLE joker');
        $this->addSql('DROP TABLE olympix');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE quiz_answer');
        $this->addSql('DROP TABLE quiz_question');
        $this->addSql('DROP TABLE split_or_steal_match');
        $this->addSql('DROP TABLE tournament');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
