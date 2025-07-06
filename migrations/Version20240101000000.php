<?php

// migrations/Version20240101000000.php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create all tables for Saufolympix system';
    }

    public function up(Schema $schema): void
    {
        // Create olympix table
        $this->addSql('CREATE TABLE olympix (
            id INT AUTO_INCREMENT NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            created_at DATETIME NOT NULL, 
            is_active TINYINT(1) NOT NULL, 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create player table
        $this->addSql('CREATE TABLE player (
            id INT AUTO_INCREMENT NOT NULL, 
            olympix_id INT NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            total_points INT NOT NULL, 
            joker_double_used TINYINT(1) NOT NULL, 
            joker_swap_used TINYINT(1) NOT NULL, 
            INDEX IDX_98197A65A8C6C4D4 (olympix_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create game table
        $this->addSql('CREATE TABLE game (
            id INT AUTO_INCREMENT NOT NULL, 
            olympix_id INT NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            game_type VARCHAR(50) NOT NULL, 
            team_size INT DEFAULT NULL, 
            points_distribution JSON DEFAULT NULL, 
            status VARCHAR(50) NOT NULL, 
            order_position INT NOT NULL, 
            INDEX IDX_232B318CA8C6C4D4 (olympix_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create game_result table
        $this->addSql('CREATE TABLE game_result (
            id INT AUTO_INCREMENT NOT NULL, 
            game_id INT NOT NULL, 
            player_id INT NOT NULL, 
            points INT NOT NULL, 
            position INT NOT NULL, 
            team_id INT DEFAULT NULL, 
            joker_double_applied TINYINT(1) NOT NULL, 
            INDEX IDX_E05F5F82E48FD905 (game_id), 
            INDEX IDX_E05F5F8299E6F5DF (player_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create quiz_question table
        $this->addSql('CREATE TABLE quiz_question (
            id INT AUTO_INCREMENT NOT NULL, 
            game_id INT NOT NULL, 
            question LONGTEXT NOT NULL, 
            correct_answer NUMERIC(10, 2) NOT NULL, 
            order_position INT NOT NULL, 
            INDEX IDX_6033B00BE48FD905 (game_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create quiz_answer table
        $this->addSql('CREATE TABLE quiz_answer (
            id INT AUTO_INCREMENT NOT NULL, 
            quiz_question_id INT NOT NULL, 
            player_id INT NOT NULL, 
            answer NUMERIC(10, 2) NOT NULL, 
            points_earned INT NOT NULL, 
            answered_at DATETIME NOT NULL, 
            INDEX IDX_4E0A2AA58F8E6E3D (quiz_question_id), 
            INDEX IDX_4E0A2AA599E6F5DF (player_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create joker table
        $this->addSql('CREATE TABLE joker (
            id INT AUTO_INCREMENT NOT NULL, 
            player_id INT NOT NULL, 
            game_id INT NOT NULL, 
            target_player_id INT DEFAULT NULL, 
            joker_type VARCHAR(50) NOT NULL, 
            is_used TINYINT(1) NOT NULL, 
            used_at DATETIME DEFAULT NULL, 
            INDEX IDX_B7A9B05999E6F5DF (player_id), 
            INDEX IDX_B7A9B059E48FD905 (game_id), 
            INDEX IDX_B7A9B059D9E3A777 (target_player_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create tournament table
        $this->addSql('CREATE TABLE tournament (
            id INT AUTO_INCREMENT NOT NULL, 
            game_id INT NOT NULL, 
            bracket_data JSON NOT NULL, 
            current_round INT NOT NULL, 
            is_completed TINYINT(1) NOT NULL, 
            UNIQUE INDEX UNIQ_BD5FB8D9E48FD905 (game_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A8C6C4D4 FOREIGN KEY (olympix_id) REFERENCES olympix (id)');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318CA8C6C4D4 FOREIGN KEY (olympix_id) REFERENCES olympix (id)');
        $this->addSql('ALTER TABLE game_result ADD CONSTRAINT FK_E05F5F82E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE game_result ADD CONSTRAINT FK_E05F5F8299E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_6033B00BE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_4E0A2AA58F8E6E3D FOREIGN KEY (quiz_question_id) REFERENCES quiz_question (id)');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_4E0A2AA599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_B7A9B05999E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_B7A9B059E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE joker ADD CONSTRAINT FK_B7A9B059D9E3A777 FOREIGN KEY (target_player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D9E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        
        // Add indexes for better performance
        $this->addSql('CREATE INDEX IDX_98197A65A8C6C4D4_POINTS ON player (olympix_id, total_points DESC)');
        $this->addSql('CREATE INDEX IDX_232B318CA8C6C4D4_ORDER ON game (olympix_id, order_position ASC)');
        $this->addSql('CREATE INDEX IDX_232B318CA8C6C4D4_STATUS ON game (olympix_id, status)');
        $this->addSql('CREATE INDEX IDX_E05F5F82E48FD905_POSITION ON game_result (game_id, position ASC)');
        $this->addSql('CREATE INDEX IDX_6033B00BE48FD905_ORDER ON quiz_question (game_id, order_position ASC)');
        $this->addSql('CREATE INDEX IDX_B7A9B059E48FD905_TYPE ON joker (game_id, joker_type)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65A8C6C4D4');
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318CA8C6C4D4');
        $this->addSql('ALTER TABLE game_result DROP FOREIGN KEY FK_E05F5F82E48FD905');
        $this->addSql('ALTER TABLE game_result DROP FOREIGN KEY FK_E05F5F8299E6F5DF');
        $this->addSql('ALTER TABLE quiz_question DROP FOREIGN KEY FK_6033B00BE48FD905');
        $this->addSql('ALTER TABLE quiz_answer DROP FOREIGN KEY FK_4E0A2AA58F8E6E3D');
        $this->addSql('ALTER TABLE quiz_answer DROP FOREIGN KEY FK_4E0A2AA599E6F5DF');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_B7A9B05999E6F5DF');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_B7A9B059E48FD905');
        $this->addSql('ALTER TABLE joker DROP FOREIGN KEY FK_B7A9B059D9E3A777');
        $this->addSql('ALTER TABLE tournament DROP FOREIGN KEY FK_BD5FB8D9E48FD905');
        
        // Drop tables
        $this->addSql('DROP TABLE tournament');
        $this->addSql('DROP TABLE joker');
        $this->addSql('DROP TABLE quiz_answer');
        $this->addSql('DROP TABLE quiz_question');
        $this->addSql('DROP TABLE game_result');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE olympix');
    }
}