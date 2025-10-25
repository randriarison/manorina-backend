<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025040621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, author_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', parent_article_id INT DEFAULT NULL, updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(25) NOT NULL, content_mg LONGTEXT DEFAULT NULL, content_en LONGTEXT DEFAULT NULL, title_mg VARCHAR(255) DEFAULT NULL, title_en VARCHAR(255) DEFAULT NULL, initial_lang VARCHAR(3) DEFAULT NULL, article_type VARCHAR(255) NOT NULL, INDEX IDX_23A0E66F675F31B (author_id), INDEX IDX_23A0E6635D702CC (parent_article_id), INDEX IDX_23A0E66896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6635D702CC FOREIGN KEY (parent_article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F675F31B');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6635D702CC');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66896DBBDE');
        $this->addSql('DROP TABLE article');
    }
}
