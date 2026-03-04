<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225202154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE exercise');
        $this->addSql('ALTER TABLE exercice ADD skill_codes JSON DEFAULT \'[]\' NOT NULL, CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE exercise_ai_feedback CHANGE model model VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT NULL, CHANGE category category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data JSON NOT NULL, CHANGE grammar_data grammar_data JSON NOT NULL, CHANGE video_name video_name VARCHAR(255) DEFAULT NULL, CHANGE thumb_name thumb_name VARCHAR(255) DEFAULT NULL, CHANGE resource_name resource_name VARCHAR(255) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE mock_test CHANGE test_category test_category VARCHAR(50) DEFAULT \'QCM\' NOT NULL, CHANGE level level VARCHAR(50) DEFAULT \'Beginner\' NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE metadata metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz_attempt CHANGE finished_at finished_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE sla_deadline sla_deadline DATETIME DEFAULT NULL, CHANGE resolved_at resolved_at DATETIME DEFAULT NULL, CHANGE satisfaction_rated_at satisfaction_rated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation_audit CHANGE discriminator discriminator VARCHAR(255) DEFAULT NULL, CHANGE transaction_hash transaction_hash VARCHAR(40) DEFAULT NULL, CHANGE diffs diffs JSON DEFAULT NULL, CHANGE blame_id blame_id VARCHAR(255) DEFAULT NULL, CHANGE blame_user blame_user VARCHAR(255) DEFAULT NULL, CHANGE blame_user_fqdn blame_user_fqdn VARCHAR(255) DEFAULT NULL, CHANGE blame_user_firewall blame_user_firewall VARCHAR(100) DEFAULT NULL, CHANGE ip ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE recommendation_session CHANGE weak_skill_codes weak_skill_codes JSON NOT NULL, CHANGE recommended_exercise_ids recommended_exercise_ids JSON NOT NULL');
        $this->addSql('ALTER TABLE support_audit_logs CHANGE metadata metadata JSON DEFAULT NULL, CHANGE old_value old_value VARCHAR(50) DEFAULT NULL, CHANGE new_value new_value VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE test_question CHANGE question_type question_type VARCHAR(50) DEFAULT \'qcm\' NOT NULL, CHANGE options options JSON NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE test_result CHANGE ai_predicted_score ai_predicted_score DOUBLE PRECISION DEFAULT NULL, CHANGE ai_weakness_report ai_weakness_report JSON DEFAULT NULL, CHANGE ai_correction ai_correction JSON DEFAULT NULL, CHANGE ai_note ai_note DOUBLE PRECISION DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL, CHANGE banned_until banned_until DATETIME DEFAULT NULL, CHANGE ban_reason ban_reason VARCHAR(255) DEFAULT NULL, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT NULL, CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT NULL, CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT NULL, CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT NULL, CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT NULL, CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT NULL, CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT NULL, CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE exercice DROP skill_codes, CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE exercise_ai_feedback CHANGE model model VARCHAR(120) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT \'NULL\', CHANGE category category VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE grammar_data grammar_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE video_name video_name VARCHAR(255) DEFAULT \'NULL\', CHANGE thumb_name thumb_name VARCHAR(255) DEFAULT \'NULL\', CHANGE resource_name resource_name VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE mock_test CHANGE test_category test_category VARCHAR(50) DEFAULT \'\'\'QCM\'\'\' NOT NULL, CHANGE level level VARCHAR(50) DEFAULT \'\'\'Beginner\'\'\' NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT \'NULL\', CHANGE metadata metadata LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE quiz_attempt CHANGE finished_at finished_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reclamation CHANGE sla_deadline sla_deadline DATETIME DEFAULT \'NULL\', CHANGE resolved_at resolved_at DATETIME DEFAULT \'NULL\', CHANGE satisfaction_rated_at satisfaction_rated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reclamation_audit CHANGE discriminator discriminator VARCHAR(255) DEFAULT \'NULL\', CHANGE transaction_hash transaction_hash VARCHAR(40) DEFAULT \'NULL\', CHANGE diffs diffs LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE blame_id blame_id VARCHAR(255) DEFAULT \'NULL\', CHANGE blame_user blame_user VARCHAR(255) DEFAULT \'NULL\', CHANGE blame_user_fqdn blame_user_fqdn VARCHAR(255) DEFAULT \'NULL\', CHANGE blame_user_firewall blame_user_firewall VARCHAR(100) DEFAULT \'NULL\', CHANGE ip ip VARCHAR(45) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE recommendation_session CHANGE weak_skill_codes weak_skill_codes LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE recommended_exercise_ids recommended_exercise_ids LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE support_audit_logs CHANGE metadata metadata LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE old_value old_value VARCHAR(50) DEFAULT \'NULL\', CHANGE new_value new_value VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_question CHANGE question_type question_type VARCHAR(50) DEFAULT \'\'\'qcm\'\'\' NOT NULL, CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_result CHANGE ai_predicted_score ai_predicted_score DOUBLE PRECISION DEFAULT \'NULL\', CHANGE ai_weakness_report ai_weakness_report LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE ai_correction ai_correction LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE ai_note ai_note DOUBLE PRECISION DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE users CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE banned_until banned_until DATETIME DEFAULT \'NULL\', CHANGE ban_reason ban_reason VARCHAR(255) DEFAULT \'NULL\', CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT \'NULL\', CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT \'NULL\', CHANGE email_verification_token email_verification_token VARCHAR(100) DEFAULT \'NULL\', CHANGE email_verification_token_expires_at email_verification_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE password_reset_token password_reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE password_reset_token_expires_at password_reset_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE stripe_customer_id stripe_customer_id VARCHAR(100) DEFAULT \'NULL\', CHANGE stripe_subscription_id stripe_subscription_id VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
    }
}
