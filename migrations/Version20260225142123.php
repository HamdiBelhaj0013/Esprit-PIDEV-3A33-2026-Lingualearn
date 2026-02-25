<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225142123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certificate (id INT AUTO_INCREMENT NOT NULL, avg_score DOUBLE PRECISION NOT NULL, unique_code VARCHAR(36) NOT NULL, issued_at DATETIME NOT NULL, user_id INT NOT NULL, platform_language_id INT NOT NULL, UNIQUE INDEX UNIQ_219CDA4AB19D0B94 (unique_code), INDEX IDX_219CDA4AA76ED395 (user_id), INDEX IDX_219CDA4ACD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercise_ai_feedback (id INT AUTO_INCREMENT NOT NULL, is_correct TINYINT NOT NULL, student_answer LONGTEXT DEFAULT NULL, correct_answer LONGTEXT DEFAULT NULL, ai_explanation LONGTEXT DEFAULT NULL, ai_correction LONGTEXT DEFAULT NULL, ai_tip LONGTEXT DEFAULT NULL, ai_example LONGTEXT DEFAULT NULL, provider VARCHAR(50) NOT NULL, model VARCHAR(120) DEFAULT NULL, prompt_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, quiz_attempt_id INT NOT NULL, exercise_id INT NOT NULL, INDEX IDX_803DD2BCE934951A (exercise_id), INDEX idx_exercise_ai_feedback_attempt (quiz_attempt_id), INDEX idx_exercise_ai_feedback_user (user_id), UNIQUE INDEX uq_exercise_ai_feedback_user_attempt_exercise_prompt (user_id, quiz_attempt_id, exercise_id, prompt_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercise_attempt (id INT AUTO_INCREMENT NOT NULL, is_correct TINYINT NOT NULL, given_answer LONGTEXT DEFAULT NULL, points INT NOT NULL, created_at DATETIME NOT NULL, time_spent_seconds INT DEFAULT NULL, quiz_attempt_id INT NOT NULL, exercise_id INT NOT NULL, INDEX IDX_C2F9F523E934951A (exercise_id), INDEX idx_exercise_attempt_quiz_attempt (quiz_attempt_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_attempt (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, state VARCHAR(50) NOT NULL, attempt_number SMALLINT NOT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, best_score INT NOT NULL, user_id INT NOT NULL, quiz_id INT NOT NULL, lesson_id INT DEFAULT NULL, second_chance_exercise_id INT DEFAULT NULL, INDEX IDX_AB6AFC6A76ED395 (user_id), INDEX IDX_AB6AFC6853CD175 (quiz_id), INDEX IDX_AB6AFC6CDF80196 (lesson_id), INDEX IDX_AB6AFC613A460D6 (second_chance_exercise_id), INDEX idx_quiz_attempt_user_quiz (user_id, quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_schedule (id INT AUTO_INCREMENT NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_8CFCE8BECB944F1A (student_id), INDEX IDX_8CFCE8BE853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recommendation_session (id INT AUTO_INCREMENT NOT NULL, weak_skill_codes JSON NOT NULL, recommended_exercise_ids JSON NOT NULL, ai_feedback LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_recommendation_session_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill_profile (id INT AUTO_INCREMENT NOT NULL, skill_code VARCHAR(100) NOT NULL, mastery SMALLINT DEFAULT 0 NOT NULL, attempts_count INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_skill_profile_user (user_id), UNIQUE INDEX uq_skill_profile_user_skill (user_id, skill_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4ACD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCF8FE9957 FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_ai_feedback ADD CONSTRAINT FK_803DD2BCE934951A FOREIGN KEY (exercise_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attempt ADD CONSTRAINT FK_C2F9F523F8FE9957 FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attempt ADD CONSTRAINT FK_C2F9F523E934951A FOREIGN KEY (exercise_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC613A460D6 FOREIGN KEY (second_chance_exercise_id) REFERENCES exercice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_schedule ADD CONSTRAINT FK_8CFCE8BECB944F1A FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_schedule ADD CONSTRAINT FK_8CFCE8BE853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recommendation_session ADD CONSTRAINT FK_8D27DC78A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE skill_profile ADD CONSTRAINT FK_9BA23426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT NULL, CHANGE category category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD video_name VARCHAR(255) DEFAULT NULL, ADD thumb_name VARCHAR(255) DEFAULT NULL, ADD resource_name VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE vocabulary_data vocabulary_data JSON NOT NULL, CHANGE grammar_data grammar_data JSON NOT NULL');
        $this->addSql('ALTER TABLE mock_test ADD test_category VARCHAR(50) DEFAULT \'QCM\' NOT NULL, ADD level VARCHAR(50) DEFAULT \'Beginner\' NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE mock_test ADD CONSTRAINT FK_D9FB90A1CD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('CREATE INDEX IDX_D9FB90A1CD56BC53 ON mock_test (platform_language_id)');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE metadata metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE test_question ADD question_type VARCHAR(50) DEFAULT \'qcm\' NOT NULL, ADD reading_passage LONGTEXT DEFAULT NULL, ADD audio_text LONGTEXT DEFAULT NULL, ADD writing_subject LONGTEXT DEFAULT NULL, CHANGE options options JSON NOT NULL, CHANGE correct_answer correct_answer LONGTEXT DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE test_result ADD overall_score DOUBLE PRECISION NOT NULL, ADD ai_predicted_score DOUBLE PRECISION DEFAULT NULL, ADD ai_weakness_report JSON DEFAULT NULL, ADD ai_correction JSON DEFAULT NULL, ADD ai_note DOUBLE PRECISION DEFAULT NULL, ADD date_taken DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD mock_test_id INT NOT NULL, ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63DE5D55330 FOREIGN KEY (mock_test_id) REFERENCES mock_test (id)');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_84B3C63DE5D55330 ON test_result (mock_test_id)');
        $this->addSql('CREATE INDEX IDX_84B3C63DA76ED395 ON test_result (user_id)');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE last_quiz_score last_quiz_score INT NOT NULL');
        $this->addSql('ALTER TABLE users ADD stripe_customer_id VARCHAR(100) DEFAULT NULL, ADD stripe_subscription_id VARCHAR(100) DEFAULT NULL, CHANGE roles roles JSON NOT NULL, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT NULL, CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT NULL, CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT NULL, CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT NULL, CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT NULL, CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4AA76ED395');
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4ACD56BC53');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCA76ED395');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCF8FE9957');
        $this->addSql('ALTER TABLE exercise_ai_feedback DROP FOREIGN KEY FK_803DD2BCE934951A');
        $this->addSql('ALTER TABLE exercise_attempt DROP FOREIGN KEY FK_C2F9F523F8FE9957');
        $this->addSql('ALTER TABLE exercise_attempt DROP FOREIGN KEY FK_C2F9F523E934951A');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6A76ED395');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6853CD175');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6CDF80196');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC613A460D6');
        $this->addSql('ALTER TABLE quiz_schedule DROP FOREIGN KEY FK_8CFCE8BECB944F1A');
        $this->addSql('ALTER TABLE quiz_schedule DROP FOREIGN KEY FK_8CFCE8BE853CD175');
        $this->addSql('ALTER TABLE recommendation_session DROP FOREIGN KEY FK_8D27DC78A76ED395');
        $this->addSql('ALTER TABLE skill_profile DROP FOREIGN KEY FK_9BA23426A76ED395');
        $this->addSql('DROP TABLE certificate');
        $this->addSql('DROP TABLE exercise_ai_feedback');
        $this->addSql('DROP TABLE exercise_attempt');
        $this->addSql('DROP TABLE quiz_attempt');
        $this->addSql('DROP TABLE quiz_schedule');
        $this->addSql('DROP TABLE recommendation_session');
        $this->addSql('DROP TABLE skill_profile');
        $this->addSql('ALTER TABLE exercice CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT \'NULL\', CHANGE category category VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE lesson DROP video_name, DROP thumb_name, DROP resource_name, DROP updated_at, CHANGE vocabulary_data vocabulary_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE grammar_data grammar_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE mock_test DROP FOREIGN KEY FK_D9FB90A1CD56BC53');
        $this->addSql('DROP INDEX IDX_D9FB90A1CD56BC53 ON mock_test');
        $this->addSql('ALTER TABLE mock_test DROP test_category, DROP level, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT \'NULL\', CHANGE metadata metadata LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_question DROP question_type, DROP reading_passage, DROP audio_text, DROP writing_subject, CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE correct_answer correct_answer VARCHAR(255) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63DE5D55330');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63DA76ED395');
        $this->addSql('DROP INDEX IDX_84B3C63DE5D55330 ON test_result');
        $this->addSql('DROP INDEX IDX_84B3C63DA76ED395 ON test_result');
        $this->addSql('ALTER TABLE test_result DROP overall_score, DROP ai_predicted_score, DROP ai_weakness_report, DROP ai_correction, DROP ai_note, DROP date_taken, DROP updated_at, DROP mock_test_id, DROP user_id');
        $this->addSql('ALTER TABLE users DROP stripe_customer_id, DROP stripe_subscription_id, CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT \'NULL\', CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT \'NULL\', CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT \'NULL\', CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE last_quiz_score last_quiz_score INT DEFAULT 0 NOT NULL, CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
    }
}
