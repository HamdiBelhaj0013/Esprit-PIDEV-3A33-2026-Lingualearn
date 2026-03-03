<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225190729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certificate (id INT AUTO_INCREMENT NOT NULL, avg_score DOUBLE PRECISION NOT NULL, unique_code VARCHAR(36) NOT NULL, issued_at DATETIME NOT NULL, user_id INT NOT NULL, platform_language_id INT NOT NULL, UNIQUE INDEX UNIQ_219CDA4AB19D0B94 (unique_code), INDEX IDX_219CDA4AA76ED395 (user_id), INDEX IDX_219CDA4ACD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, level VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, published_at DATETIME NOT NULL, author_id INT DEFAULT NULL, platform_language_id INT NOT NULL, INDEX IDX_169E6FB9F675F31B (author_id), INDEX IDX_169E6FB9CD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercice (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, question LONGTEXT NOT NULL, options JSON NOT NULL, correct_answer VARCHAR(255) NOT NULL, ai_generated TINYINT NOT NULL, enabled TINYINT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_E418C74D853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercise_ai_feedback (id INT AUTO_INCREMENT NOT NULL, is_correct TINYINT NOT NULL, student_answer LONGTEXT DEFAULT NULL, correct_answer LONGTEXT DEFAULT NULL, ai_explanation LONGTEXT DEFAULT NULL, ai_correction LONGTEXT DEFAULT NULL, ai_tip LONGTEXT DEFAULT NULL, ai_example LONGTEXT DEFAULT NULL, provider VARCHAR(50) NOT NULL, model VARCHAR(120) DEFAULT NULL, prompt_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, quiz_attempt_id INT NOT NULL, exercise_id INT NOT NULL, INDEX IDX_803DD2BCE934951A (exercise_id), INDEX idx_exercise_ai_feedback_attempt (quiz_attempt_id), INDEX idx_exercise_ai_feedback_user (user_id), UNIQUE INDEX uq_exercise_ai_feedback_user_attempt_exercise_prompt (user_id, quiz_attempt_id, exercise_id, prompt_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercise_attempt (id INT AUTO_INCREMENT NOT NULL, is_correct TINYINT NOT NULL, given_answer LONGTEXT DEFAULT NULL, points INT NOT NULL, created_at DATETIME NOT NULL, time_spent_seconds INT DEFAULT NULL, quiz_attempt_id INT NOT NULL, exercise_id INT NOT NULL, INDEX IDX_C2F9F523E934951A (exercise_id), INDEX idx_exercise_attempt_quiz_attempt (quiz_attempt_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE faq (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(255) NOT NULL, answer LONGTEXT NOT NULL, subject VARCHAR(50) DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, submitted_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, author_id INT NOT NULL, platform_language_id INT NOT NULL, posted_at DATETIME NOT NULL, is_active TINYINT NOT NULL, view_count INT NOT NULL, reply_count INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_reply (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, author_id INT NOT NULL, replied_at DATETIME NOT NULL, is_active TINYINT NOT NULL, is_best_answer TINYINT NOT NULL, post_id INT NOT NULL, INDEX IDX_E5DC60374B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE languages (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(10) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE learning_stats (id INT AUTO_INCREMENT NOT NULL, total_minutes_studied INT NOT NULL, words_learned INT NOT NULL, total_xp INT NOT NULL, last_study_session DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A9D52C90A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lesson (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, vocabulary_data JSON NOT NULL, grammar_data JSON NOT NULL, xp_reward INT NOT NULL, video_name VARCHAR(255) DEFAULT NULL, thumb_name VARCHAR(255) DEFAULT NULL, resource_name VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, course_id INT NOT NULL, INDEX IDX_F87474F3591CC992 (course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mock_test (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, test_type VARCHAR(50) NOT NULL, test_category VARCHAR(50) DEFAULT \'QCM\' NOT NULL, level VARCHAR(50) DEFAULT \'Beginner\' NOT NULL, duration_minutes INT NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, platform_language_id INT NOT NULL, INDEX IDX_D9FB90A1CD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, metadata JSON DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_6000B0D3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE platform_language (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(10) NOT NULL, flag_url VARCHAR(255) NOT NULL, is_enabled TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, passing_score INT NOT NULL, question_count INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, enabled TINYINT NOT NULL, lesson_id INT DEFAULT NULL, INDEX IDX_A412FA92CDF80196 (lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_attempt (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, state VARCHAR(50) NOT NULL, attempt_number SMALLINT NOT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, best_score INT NOT NULL, user_id INT NOT NULL, quiz_id INT NOT NULL, lesson_id INT DEFAULT NULL, second_chance_exercise_id INT DEFAULT NULL, INDEX IDX_AB6AFC6A76ED395 (user_id), INDEX IDX_AB6AFC6853CD175 (quiz_id), INDEX IDX_AB6AFC6CDF80196 (lesson_id), INDEX IDX_AB6AFC613A460D6 (second_chance_exercise_id), INDEX idx_quiz_attempt_user_quiz (user_id, quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_schedule (id INT AUTO_INCREMENT NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_8CFCE8BECB944F1A (student_id), INDEX IDX_8CFCE8BE853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(100) NOT NULL, message_body LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, submitted_at DATETIME NOT NULL, priority VARCHAR(10) NOT NULL, sla_deadline DATETIME DEFAULT NULL, is_late TINYINT DEFAULT 0 NOT NULL, resolved_at DATETIME DEFAULT NULL, satisfaction_score INT DEFAULT NULL, satisfaction_comment LONGTEXT DEFAULT NULL, satisfaction_rated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_CE606404A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reclamation_audit (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSON DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX type_98d6d4200acdceaafb2260d0ea2a31e5_idx (type), INDEX object_id_98d6d4200acdceaafb2260d0ea2a31e5_idx (object_id), INDEX discriminator_98d6d4200acdceaafb2260d0ea2a31e5_idx (discriminator), INDEX transaction_hash_98d6d4200acdceaafb2260d0ea2a31e5_idx (transaction_hash), INDEX blame_id_98d6d4200acdceaafb2260d0ea2a31e5_idx (blame_id), INDEX created_at_98d6d4200acdceaafb2260d0ea2a31e5_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recommendation_session (id INT AUTO_INCREMENT NOT NULL, weak_skill_codes JSON NOT NULL, recommended_exercise_ids JSON NOT NULL, ai_feedback LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_recommendation_session_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill_profile (id INT AUTO_INCREMENT NOT NULL, skill_code VARCHAR(100) NOT NULL, mastery SMALLINT DEFAULT 0 NOT NULL, attempts_count INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_skill_profile_user (user_id), UNIQUE INDEX uq_skill_profile_user_skill (user_id, skill_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_audit_logs (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(50) NOT NULL, description VARCHAR(255) NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, old_value VARCHAR(50) DEFAULT NULL, new_value VARCHAR(50) DEFAULT NULL, reclamation_id INT DEFAULT NULL, performed_by_id INT DEFAULT NULL, INDEX IDX_F49BEC7A2D6BA2D9 (reclamation_id), INDEX IDX_F49BEC7A2E65C292 (performed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_notifications (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, reclamation_id INT DEFAULT NULL, INDEX IDX_385347A0A76ED395 (user_id), INDEX IDX_385347A02D6BA2D9 (reclamation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_response (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, responded_at DATETIME NOT NULL, reclamation_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_8ACD80C42D6BA2D9 (reclamation_id), INDEX IDX_8ACD80C4F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE test_question (id INT AUTO_INCREMENT NOT NULL, section_category VARCHAR(100) NOT NULL, question_type VARCHAR(50) DEFAULT \'qcm\' NOT NULL, question_text LONGTEXT NOT NULL, reading_passage LONGTEXT DEFAULT NULL, audio_text LONGTEXT DEFAULT NULL, writing_subject LONGTEXT DEFAULT NULL, options JSON NOT NULL, correct_answer LONGTEXT DEFAULT NULL, points INT NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, mock_test_id INT NOT NULL, INDEX IDX_23944218E5D55330 (mock_test_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE test_result (id INT AUTO_INCREMENT NOT NULL, overall_score DOUBLE PRECISION NOT NULL, ai_predicted_score DOUBLE PRECISION DEFAULT NULL, ai_weakness_report JSON DEFAULT NULL, ai_correction JSON DEFAULT NULL, ai_note DOUBLE PRECISION DEFAULT NULL, date_taken DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, mock_test_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_84B3C63DE5D55330 (mock_test_id), INDEX IDX_84B3C63DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_language (id INT AUTO_INCREMENT NOT NULL, proficiency_level VARCHAR(20) NOT NULL, is_native TINYINT NOT NULL, added_at DATETIME NOT NULL, user_id INT NOT NULL, platform_language_id INT NOT NULL, INDEX IDX_345695B5A76ED395 (user_id), INDEX IDX_345695B5CD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_lesson_status (id INT AUTO_INCREMENT NOT NULL, is_completed TINYINT NOT NULL, best_quiz_score INT NOT NULL, last_quiz_score INT NOT NULL, completed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, lesson_id INT NOT NULL, INDEX IDX_E618381BA76ED395 (user_id), INDEX IDX_E618381BCDF80196 (lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, is_banned TINYINT DEFAULT 0 NOT NULL, banned_until DATETIME DEFAULT NULL, ban_reason VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, subscription_plan VARCHAR(50) NOT NULL, subscription_expiry DATETIME DEFAULT NULL, is_premium TINYINT NOT NULL, last_payment_status VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, email_verification_token VARCHAR(100) DEFAULT NULL, email_verification_token_expires_at DATETIME DEFAULT NULL, password_reset_token VARCHAR(100) DEFAULT NULL, password_reset_token_expires_at DATETIME DEFAULT NULL, stripe_customer_id VARCHAR(100) DEFAULT NULL, stripe_subscription_id VARCHAR(100) DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4ACD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9CD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE exercice ADD CONSTRAINT FK_E418C74D853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCF8FE9957 FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCE934951A FOREIGN KEY (exercise_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attempt ADD CONSTRAINT FK_C2F9F523F8FE9957 FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attempt ADD CONSTRAINT FK_C2F9F523E934951A FOREIGN KEY (exercise_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC60374B89032C FOREIGN KEY (post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE learning_stats ADD CONSTRAINT FK_A9D52C90A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F3591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE mock_test ADD CONSTRAINT FK_D9FB90A1CD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA92CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC613A460D6 FOREIGN KEY (second_chance_exercise_id) REFERENCES exercice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_schedule ADD CONSTRAINT FK_8CFCE8BECB944F1A FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_schedule ADD CONSTRAINT FK_8CFCE8BE853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE recommendation_session ADD CONSTRAINT FK_8D27DC78A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE skill_profile ADD CONSTRAINT FK_9BA23426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_audit_logs ADD CONSTRAINT FK_F49BEC7A2D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_audit_logs ADD CONSTRAINT FK_F49BEC7A2E65C292 FOREIGN KEY (performed_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_notifications ADD CONSTRAINT FK_385347A0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_notifications ADD CONSTRAINT FK_385347A02D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_response ADD CONSTRAINT FK_8ACD80C42D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id)');
        $this->addSql('ALTER TABLE support_response ADD CONSTRAINT FK_8ACD80C4F675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE test_question ADD CONSTRAINT FK_23944218E5D55330 FOREIGN KEY (mock_test_id) REFERENCES mock_test (id)');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63DE5D55330 FOREIGN KEY (mock_test_id) REFERENCES mock_test (id)');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_language ADD CONSTRAINT FK_345695B5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_language ADD CONSTRAINT FK_345695B5CD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE user_lesson_status ADD CONSTRAINT FK_E618381BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_lesson_status ADD CONSTRAINT FK_E618381BCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4AA76ED395');
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4ACD56BC53');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB9F675F31B');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB9CD56BC53');
        $this->addSql('ALTER TABLE exercice DROP FOREIGN KEY FK_E418C74D853CD175');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCA76ED395');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCF8FE9957');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCE934951A');
        $this->addSql('ALTER TABLE exercise_attempt DROP FOREIGN KEY FK_C2F9F523F8FE9957');
        $this->addSql('ALTER TABLE exercise_attempt DROP FOREIGN KEY FK_C2F9F523E934951A');
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC60374B89032C');
        $this->addSql('ALTER TABLE learning_stats DROP FOREIGN KEY FK_A9D52C90A76ED395');
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F3591CC992');
        $this->addSql('ALTER TABLE mock_test DROP FOREIGN KEY FK_D9FB90A1CD56BC53');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA92CDF80196');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6A76ED395');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6853CD175');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6CDF80196');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC613A460D6');
        $this->addSql('ALTER TABLE quiz_schedule DROP FOREIGN KEY FK_8CFCE8BECB944F1A');
        $this->addSql('ALTER TABLE quiz_schedule DROP FOREIGN KEY FK_8CFCE8BE853CD175');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
        $this->addSql('ALTER TABLE recommendation_session DROP FOREIGN KEY FK_8D27DC78A76ED395');
        $this->addSql('ALTER TABLE skill_profile DROP FOREIGN KEY FK_9BA23426A76ED395');
        $this->addSql('ALTER TABLE support_audit_logs DROP FOREIGN KEY FK_F49BEC7A2D6BA2D9');
        $this->addSql('ALTER TABLE support_audit_logs DROP FOREIGN KEY FK_F49BEC7A2E65C292');
        $this->addSql('ALTER TABLE support_notifications DROP FOREIGN KEY FK_385347A0A76ED395');
        $this->addSql('ALTER TABLE support_notifications DROP FOREIGN KEY FK_385347A02D6BA2D9');
        $this->addSql('ALTER TABLE support_response DROP FOREIGN KEY FK_8ACD80C42D6BA2D9');
        $this->addSql('ALTER TABLE support_response DROP FOREIGN KEY FK_8ACD80C4F675F31B');
        $this->addSql('ALTER TABLE test_question DROP FOREIGN KEY FK_23944218E5D55330');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63DE5D55330');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63DA76ED395');
        $this->addSql('ALTER TABLE user_language DROP FOREIGN KEY FK_345695B5A76ED395');
        $this->addSql('ALTER TABLE user_language DROP FOREIGN KEY FK_345695B5CD56BC53');
        $this->addSql('ALTER TABLE user_lesson_status DROP FOREIGN KEY FK_E618381BA76ED395');
        $this->addSql('ALTER TABLE user_lesson_status DROP FOREIGN KEY FK_E618381BCDF80196');
        $this->addSql('DROP TABLE certificate');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE exercise_ai_feedback');
        $this->addSql('DROP TABLE exercise_attempt');
        $this->addSql('DROP TABLE faq');
        $this->addSql('DROP TABLE forum_post');
        $this->addSql('DROP TABLE forum_reply');
        $this->addSql('DROP TABLE languages');
        $this->addSql('DROP TABLE learning_stats');
        $this->addSql('DROP TABLE lesson');
        $this->addSql('DROP TABLE mock_test');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE platform_language');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE quiz_attempt');
        $this->addSql('DROP TABLE quiz_schedule');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE reclamation_audit');
        $this->addSql('DROP TABLE recommendation_session');
        $this->addSql('DROP TABLE skill_profile');
        $this->addSql('DROP TABLE support_audit_logs');
        $this->addSql('DROP TABLE support_notifications');
        $this->addSql('DROP TABLE support_response');
        $this->addSql('DROP TABLE test_question');
        $this->addSql('DROP TABLE test_result');
        $this->addSql('DROP TABLE user_language');
        $this->addSql('DROP TABLE user_lesson_status');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
