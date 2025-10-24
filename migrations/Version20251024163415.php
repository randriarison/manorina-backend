<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024163415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change User ID from integer to UUID (WARNING: This will drop all existing user data)';
    }

    public function up(Schema $schema): void
    {
        // Warning: This will drop all existing user data and recreate the table with UUID
        // If you have existing users, backup the data first
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', 
            email VARCHAR(180) NOT NULL, 
            roles JSON NOT NULL, 
            password VARCHAR(255) DEFAULT NULL, 
            google_id VARCHAR(255) DEFAULT NULL, 
            facebook_id VARCHAR(255) DEFAULT NULL, 
            name VARCHAR(255) DEFAULT NULL, 
            avatar VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            email_verification_token VARCHAR(255) DEFAULT NULL, 
            password_reset_token VARCHAR(255) DEFAULT NULL, 
            password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            is_email_verified TINYINT(1) NOT NULL, 
            PRIMARY KEY(id), 
            UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Warning: This will drop all user data and recreate with integer ID
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (
            id INT AUTO_INCREMENT NOT NULL, 
            email VARCHAR(180) NOT NULL, 
            roles JSON NOT NULL, 
            password VARCHAR(255) DEFAULT NULL, 
            google_id VARCHAR(255) DEFAULT NULL, 
            facebook_id VARCHAR(255) DEFAULT NULL, 
            name VARCHAR(255) DEFAULT NULL, 
            avatar VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            email_verification_token VARCHAR(255) DEFAULT NULL, 
            password_reset_token VARCHAR(255) DEFAULT NULL, 
            password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            is_email_verified TINYINT(1) NOT NULL, 
            PRIMARY KEY(id), 
            UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
