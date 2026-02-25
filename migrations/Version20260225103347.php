<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225103347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certificate (id INT AUTO_INCREMENT NOT NULL, avg_score DOUBLE PRECISION NOT NULL, unique_code VARCHAR(36) NOT NULL, issued_at DATETIME NOT NULL, user_id INT NOT NULL, platform_language_id INT NOT NULL, UNIQUE INDEX UNIQ_219CDA4AB19D0B94 (unique_code), INDEX IDX_219CDA4AA76ED395 (user_id), INDEX IDX_219CDA4ACD56BC53 (platform_language_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_219CDA4ACD56BC53 FOREIGN KEY (platform_language_id) REFERENCES platform_language (id)');
        $this->addSql('ALTER TABLE exercice CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT NULL, CHANGE category category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data JSON NOT NULL, CHANGE grammar_data grammar_data JSON NOT NULL');
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
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT NULL, CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT NULL, CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT NULL, CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT NULL, CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT NULL, CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL, CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT NULL, CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4AA76ED395');
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_219CDA4ACD56BC53');
        $this->addSql('DROP TABLE certificate');
        $this->addSql('ALTER TABLE exercice CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT \'NULL\', CHANGE category category VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE grammar_data grammar_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
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
        $this->addSql('ALTER TABLE users CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT \'NULL\', CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT \'NULL\', CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT \'NULL\', CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT \'NULL\', CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
    }
}
