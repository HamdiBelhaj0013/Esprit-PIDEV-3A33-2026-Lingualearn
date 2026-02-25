<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225151415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reclamation_audit (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSON DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX type_98d6d4200acdceaafb2260d0ea2a31e5_idx (type), INDEX object_id_98d6d4200acdceaafb2260d0ea2a31e5_idx (object_id), INDEX discriminator_98d6d4200acdceaafb2260d0ea2a31e5_idx (discriminator), INDEX transaction_hash_98d6d4200acdceaafb2260d0ea2a31e5_idx (transaction_hash), INDEX blame_id_98d6d4200acdceaafb2260d0ea2a31e5_idx (blame_id), INDEX created_at_98d6d4200acdceaafb2260d0ea2a31e5_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_audit_logs (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(50) NOT NULL, description VARCHAR(255) NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, old_value VARCHAR(50) DEFAULT NULL, new_value VARCHAR(50) DEFAULT NULL, reclamation_id INT DEFAULT NULL, performed_by_id INT DEFAULT NULL, INDEX IDX_F49BEC7A2D6BA2D9 (reclamation_id), INDEX IDX_F49BEC7A2E65C292 (performed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_notifications (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, reclamation_id INT DEFAULT NULL, INDEX IDX_385347A0A76ED395 (user_id), INDEX IDX_385347A02D6BA2D9 (reclamation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE support_audit_logs ADD CONSTRAINT FK_F49BEC7A2D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_audit_logs ADD CONSTRAINT FK_F49BEC7A2E65C292 FOREIGN KEY (performed_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_notifications ADD CONSTRAINT FK_385347A0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_notifications ADD CONSTRAINT FK_385347A02D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE exercise_ai_feedback CHANGE model model VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT NULL, CHANGE category category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data JSON NOT NULL, CHANGE grammar_data grammar_data JSON NOT NULL, CHANGE video_name video_name VARCHAR(255) DEFAULT NULL, CHANGE thumb_name thumb_name VARCHAR(255) DEFAULT NULL, CHANGE resource_name resource_name VARCHAR(255) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE mock_test CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE test_category test_category VARCHAR(50) DEFAULT \'QCM\' NOT NULL, CHANGE level level VARCHAR(50) DEFAULT \'Beginner\' NOT NULL');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE metadata metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz_attempt CHANGE finished_at finished_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation ADD priority VARCHAR(10) NOT NULL, ADD sla_deadline DATETIME DEFAULT NULL, ADD is_late TINYINT DEFAULT 0 NOT NULL, ADD resolved_at DATETIME DEFAULT NULL, ADD satisfaction_score INT DEFAULT NULL, ADD satisfaction_comment LONGTEXT DEFAULT NULL, ADD satisfaction_rated_at DATETIME DEFAULT NULL, CHANGE subject subject VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE recommendation_session CHANGE weak_skill_codes weak_skill_codes JSON NOT NULL, CHANGE recommended_exercise_ids recommended_exercise_ids JSON NOT NULL');
        $this->addSql('ALTER TABLE test_question CHANGE options options JSON NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE question_type question_type VARCHAR(50) DEFAULT \'qcm\' NOT NULL');
        $this->addSql('ALTER TABLE test_result CHANGE ai_predicted_score ai_predicted_score DOUBLE PRECISION DEFAULT NULL, CHANGE ai_weakness_report ai_weakness_report JSON DEFAULT NULL, CHANGE ai_correction ai_correction JSON DEFAULT NULL, CHANGE ai_note ai_note DOUBLE PRECISION DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD is_banned TINYINT DEFAULT 0 NOT NULL, ADD banned_until DATETIME DEFAULT NULL, ADD ban_reason VARCHAR(255) DEFAULT NULL, CHANGE roles roles JSON NOT NULL, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT NULL, CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT NULL, CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT NULL, CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT NULL, CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT NULL, CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL, CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT NULL, CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_audit_logs DROP FOREIGN KEY FK_F49BEC7A2D6BA2D9');
        $this->addSql('ALTER TABLE support_audit_logs DROP FOREIGN KEY FK_F49BEC7A2E65C292');
        $this->addSql('ALTER TABLE support_notifications DROP FOREIGN KEY FK_385347A0A76ED395');
        $this->addSql('ALTER TABLE support_notifications DROP FOREIGN KEY FK_385347A02D6BA2D9');
        $this->addSql('DROP TABLE reclamation_audit');
        $this->addSql('DROP TABLE support_audit_logs');
        $this->addSql('DROP TABLE support_notifications');
        $this->addSql('ALTER TABLE exercice CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE exercise_ai_feedback CHANGE model model VARCHAR(120) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT \'NULL\', CHANGE category category VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE grammar_data grammar_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE video_name video_name VARCHAR(255) DEFAULT \'NULL\', CHANGE thumb_name thumb_name VARCHAR(255) DEFAULT \'NULL\', CHANGE resource_name resource_name VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE mock_test CHANGE test_category test_category VARCHAR(50) DEFAULT \'\'\'QCM\'\'\' NOT NULL, CHANGE level level VARCHAR(50) DEFAULT \'\'\'Beginner\'\'\' NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT \'NULL\', CHANGE metadata metadata LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE quiz_attempt CHANGE finished_at finished_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reclamation DROP priority, DROP sla_deadline, DROP is_late, DROP resolved_at, DROP satisfaction_score, DROP satisfaction_comment, DROP satisfaction_rated_at, CHANGE subject subject VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE recommendation_session CHANGE weak_skill_codes weak_skill_codes LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE recommended_exercise_ids recommended_exercise_ids LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE test_question CHANGE question_type question_type VARCHAR(50) DEFAULT \'\'\'qcm\'\'\' NOT NULL, CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_result CHANGE ai_predicted_score ai_predicted_score DOUBLE PRECISION DEFAULT \'NULL\', CHANGE ai_weakness_report ai_weakness_report LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE ai_correction ai_correction LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE ai_note ai_note DOUBLE PRECISION DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE users DROP is_banned, DROP banned_until, DROP ban_reason, CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT \'NULL\', CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT \'NULL\', CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT \'NULL\', CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT \'NULL\', CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
    }
}
