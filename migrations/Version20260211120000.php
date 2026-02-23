<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Squashed migration — replaces:
 *   Version20260210140936 (initial schema)
 *   Version20260210143014 (course, lesson, platform_language)
 *   Version20260210153048 (remove lesson, update quiz/user_lesson_status)
 *   Version20260210163637 (forum_post, forum_reply — conflict resolved: both FKs kept)
 *   Version20260210231633 (exercice.enabled)
 *   Version20260211080000 (exercice.quiz_id, restore lesson + user_lesson_status.lesson_id)
 *   Version20260211083000 (quiz.enabled)
 *   Version20260211113411 (column type fixes)
 */
final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Squashed initial schema — full database setup for LinguaLearn';
    }

    public function up(Schema $schema): void
    {
        // -------------------------
        // Core user & auth tables
        // -------------------------
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            subscription_plan VARCHAR(50) NOT NULL,
            subscription_expiry DATETIME DEFAULT NULL,
            is_premium TINYINT NOT NULL,
            last_payment_status VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Language tables
        // -------------------------
        $this->addSql('CREATE TABLE languages (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE platform_language (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) NOT NULL,
            flag_url VARCHAR(255) NOT NULL,
            is_enabled TINYINT NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE user_languages (
            id INT AUTO_INCREMENT NOT NULL,
            proficiency_level VARCHAR(20) NOT NULL,
            is_native TINYINT NOT NULL,
            added_at DATETIME NOT NULL,
            user_id INT NOT NULL,
            language_id INT NOT NULL,
            INDEX IDX_A031DE9DA76ED395 (user_id),
            INDEX IDX_A031DE9D82F1BAF4 (language_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Course / Lesson
        // -------------------------
        $this->addSql('CREATE TABLE course (
            id INT AUTO_INCREMENT NOT NULL,
            author_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            level VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            published_at DATETIME NOT NULL,
            platform_language_id INT NOT NULL,
            INDEX IDX_169E6FB9CD56BC53 (platform_language_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE lesson (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user_lesson_status (
            id INT AUTO_INCREMENT NOT NULL,
            lesson_id INT NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            INDEX IDX_E618381BCDF80196 (lesson_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Quiz / Exercise
        // -------------------------
        $this->addSql('CREATE TABLE quiz (
            id INT AUTO_INCREMENT NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE exercice (
            id INT AUTO_INCREMENT NOT NULL,
            enabled TINYINT NOT NULL,
            quiz_id INT DEFAULT NULL,
            INDEX IDX_E418C74D853A5168 (quiz_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Forum
        // -------------------------
        $this->addSql('CREATE TABLE forum_post (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            author_id INT NOT NULL,
            platform_language_id INT NOT NULL,
            posted_at DATETIME NOT NULL,
            is_active TINYINT NOT NULL,
            view_count INT NOT NULL,
            reply_count INT NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE forum_reply (
            id INT AUTO_INCREMENT NOT NULL,
            content LONGTEXT NOT NULL,
            author_id INT NOT NULL,
            replied_at DATETIME NOT NULL,
            is_active TINYINT NOT NULL,
            is_best_answer TINYINT NOT NULL,
            post_id INT NOT NULL,
            INDEX IDX_E5DC60374B89032C (post_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Support / Reclamation / FAQ
        // -------------------------
        $this->addSql('CREATE TABLE faq (
            id INT AUTO_INCREMENT NOT NULL,
            question VARCHAR(255) NOT NULL,
            answer LONGTEXT NOT NULL,
            submitted_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE reclamation (
            id INT AUTO_INCREMENT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message_body LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            submitted_at DATETIME NOT NULL,
            user_id INT DEFAULT NULL,
            INDEX IDX_CE606404A76ED395 (user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE support_response (
            id INT AUTO_INCREMENT NOT NULL,
            message LONGTEXT NOT NULL,
            responded_at DATETIME NOT NULL,
            reclamation_id INT NOT NULL,
            author_id INT NOT NULL,
            INDEX IDX_8ACD80C42D6BA2D9 (reclamation_id),
            INDEX IDX_8ACD80C4F675F31B (author_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Notifications & Stats
        // -------------------------
        $this->addSql('CREATE TABLE notifications (
            id INT AUTO_INCREMENT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message LONGTEXT NOT NULL,
            is_read TINYINT NOT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            user_id INT NOT NULL,
            INDEX IDX_6000B0D3A76ED395 (user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE learning_stats (
            id INT AUTO_INCREMENT NOT NULL,
            total_minutes_studied INT NOT NULL,
            words_learned INT NOT NULL,
            total_xp INT NOT NULL,
            last_study_session DATETIME DEFAULT NULL,
            user_id INT NOT NULL,
            UNIQUE INDEX UNIQ_A9D52C90A76ED395 (user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Mock test / Test tables (placeholder)
        // -------------------------
        $this->addSql('CREATE TABLE mock_test (
            id INT AUTO_INCREMENT NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE test_question (
            id INT AUTO_INCREMENT NOT NULL,
            options JSON NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE test_result (
            id INT AUTO_INCREMENT NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Symfony Messenger
        // -------------------------
        $this->addSql('CREATE TABLE messenger_messages (
            id BIGINT AUTO_INCREMENT NOT NULL,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // -------------------------
        // Foreign Keys
        // -------------------------
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9CD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE exercice ADD CONSTRAINT FK_E418C74D853A5168 FOREIGN KEY (quiz_id) REFERENCES quiz (id)');
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC60374B89032C FOREIGN KEY (post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE learning_stats ADD CONSTRAINT FK_A9D52C90A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE support_response ADD CONSTRAINT FK_8ACD80C42D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id)');
        $this->addSql('ALTER TABLE support_response ADD CONSTRAINT FK_8ACD80C4F675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_languages ADD CONSTRAINT FK_A031DE9DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_languages ADD CONSTRAINT FK_A031DE9D82F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_lesson_status ADD CONSTRAINT FK_E618381BCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB9CD56BC53');
        $this->addSql('ALTER TABLE exercice DROP FOREIGN KEY FK_E418C74D853A5168');
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC60374B89032C');
        $this->addSql('ALTER TABLE learning_stats DROP FOREIGN KEY FK_A9D52C90A76ED395');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
        $this->addSql('ALTER TABLE support_response DROP FOREIGN KEY FK_8ACD80C42D6BA2D9');
        $this->addSql('ALTER TABLE support_response DROP FOREIGN KEY FK_8ACD80C4F675F31B');
        $this->addSql('ALTER TABLE user_languages DROP FOREIGN KEY FK_A031DE9DA76ED395');
        $this->addSql('ALTER TABLE user_languages DROP FOREIGN KEY FK_A031DE9D82F1BAF4');
        $this->addSql('ALTER TABLE user_lesson_status DROP FOREIGN KEY FK_E618381BCDF80196');

        // Drop all tables
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE faq');
        $this->addSql('DROP TABLE forum_post');
        $this->addSql('DROP TABLE forum_reply');
        $this->addSql('DROP TABLE languages');
        $this->addSql('DROP TABLE learning_stats');
        $this->addSql('DROP TABLE lesson');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE mock_test');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE platform_language');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE support_response');
        $this->addSql('DROP TABLE test_question');
        $this->addSql('DROP TABLE test_result');
        $this->addSql('DROP TABLE user_languages');
        $this->addSql('DROP TABLE user_lesson_status');
        $this->addSql('DROP TABLE users');
    }
}
