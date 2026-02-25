<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * QuizAttempt + ExerciseAttempt pour le module ExercisesQuizzes (Deuxième chance).
 */
final class Version20260220200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quiz_attempt and exercise_attempt tables for second chance feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quiz_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            quiz_id INT NOT NULL,
            lesson_id INT DEFAULT NULL,
            score INT NOT NULL DEFAULT 0,
            state VARCHAR(50) NOT NULL DEFAULT \'in_progress\',
            attempt_number SMALLINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            best_score INT NOT NULL DEFAULT 0,
            second_chance_exercise_id INT DEFAULT NULL,
            INDEX idx_quiz_attempt_user_quiz (user_id, quiz_id),
            INDEX IDX_quiz_attempt_lesson (lesson_id),
            INDEX IDX_quiz_attempt_second_chance_exercise (second_chance_exercise_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_quiz_attempt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT FK_quiz_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE,
            CONSTRAINT FK_quiz_attempt_lesson FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL,
            CONSTRAINT FK_quiz_attempt_second_chance_exercise FOREIGN KEY (second_chance_exercise_id) REFERENCES exercice (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exercise_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            quiz_attempt_id INT NOT NULL,
            exercise_id INT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            given_answer LONGTEXT DEFAULT NULL,
            points INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_exercise_attempt_quiz_attempt (quiz_attempt_id),
            INDEX IDX_exercise_attempt_exercise (exercise_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_exercise_attempt_quiz_attempt FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE,
            CONSTRAINT FK_exercise_attempt_exercise FOREIGN KEY (exercise_id) REFERENCES exercice (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exercise_attempt');
        $this->addSql('DROP TABLE quiz_attempt');
    }
}
