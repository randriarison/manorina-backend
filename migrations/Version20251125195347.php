<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125195347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD published_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD archived_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E665B075477 FOREIGN KEY (published_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6677BE2925 FOREIGN KEY (archived_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_23A0E665B075477 ON article (published_by_id)');
        $this->addSql('CREATE INDEX IDX_23A0E6677BE2925 ON article (archived_by_id)');
        $this->addSql('ALTER TABLE category ADD published_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD archived_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C15B075477 FOREIGN KEY (published_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C177BE2925 FOREIGN KEY (archived_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_64C19C15B075477 ON category (published_by_id)');
        $this->addSql('CREATE INDEX IDX_64C19C177BE2925 ON category (archived_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E665B075477');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6677BE2925');
        $this->addSql('DROP INDEX IDX_23A0E665B075477 ON article');
        $this->addSql('DROP INDEX IDX_23A0E6677BE2925 ON article');
        $this->addSql('ALTER TABLE article DROP published_by_id, DROP archived_by_id, DROP archived_at');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C15B075477');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C177BE2925');
        $this->addSql('DROP INDEX IDX_64C19C15B075477 ON category');
        $this->addSql('DROP INDEX IDX_64C19C177BE2925 ON category');
        $this->addSql('ALTER TABLE category DROP published_by_id, DROP archived_by_id, DROP published_at, DROP archived_at');
    }
}
