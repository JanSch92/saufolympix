<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stoppuhr-Spiel: neue Tabelle stopwatch_attempt + Zielzeit-Spalte am Spiel.
 * Bewusst über die Schema-API statt Raw-SQL, damit die Migration auf
 * MySQL/MariaDB, PostgreSQL und SQLite gleichermaßen läuft.
 */
final class Version20260718000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stoppuhr-Spiel: stopwatch_attempt-Tabelle und game.stopwatch_target';
    }

    public function up(Schema $schema): void
    {
        $game = $schema->getTable('game');
        if (!$game->hasColumn('stopwatch_target')) {
            $game->addColumn('stopwatch_target', 'decimal', [
                'precision' => 6,
                'scale' => 2,
                'notnull' => false,
            ]);
        }

        if (!$schema->hasTable('stopwatch_attempt')) {
            $table = $schema->createTable('stopwatch_attempt');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('game_id', 'integer');
            $table->addColumn('player_id', 'integer');
            $table->addColumn('elapsed_seconds', 'decimal', ['precision' => 8, 'scale' => 2]);
            $table->addColumn('created_at', 'datetime');
            $table->setPrimaryKey(['id']);
            $table->addIndex(['game_id'], 'IDX_STOPWATCH_ATTEMPT_GAME');
            $table->addIndex(['player_id'], 'IDX_STOPWATCH_ATTEMPT_PLAYER');
            $table->addUniqueIndex(['game_id', 'player_id'], 'uniq_stopwatch_attempt_game_player');
            $table->addForeignKeyConstraint('game', ['game_id'], ['id'], [], 'FK_STOPWATCH_ATTEMPT_GAME');
            $table->addForeignKeyConstraint('player', ['player_id'], ['id'], [], 'FK_STOPWATCH_ATTEMPT_PLAYER');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('stopwatch_attempt')) {
            $schema->dropTable('stopwatch_attempt');
        }

        $game = $schema->getTable('game');
        if ($game->hasColumn('stopwatch_target')) {
            $game->dropColumn('stopwatch_target');
        }
    }
}
