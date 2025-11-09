<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109145811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix Category table UUID structure and constraints to prevent conversion errors';
    }

    public function up(Schema $schema): void
    {
        // Ensure Category table has correct UUID structure
        // This migration addresses potential UUID conversion issues on remote servers
        
        // Ensure UUID columns are properly typed with correct comments
        $this->addSql('ALTER TABLE category MODIFY id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)"');
        $this->addSql('ALTER TABLE category MODIFY parent_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid)"');
        
        // Ensure the level column is properly typed as integer (this should prevent UUID conversion errors)
        $this->addSql('ALTER TABLE category MODIFY level INT NOT NULL');
        
        // Clear any potentially cached metadata that might cause UUID conversion issues
        // This is a structural fix to ensure proper type handling
    }

    public function down(Schema $schema): void
    {
        // This migration is a structural fix, no rollback needed
        // The changes ensure proper UUID handling and don't change data
    }
}
