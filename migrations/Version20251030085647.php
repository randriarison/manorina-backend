<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030085647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD root_article_id INT DEFAULT NULL, ADD children_count INT DEFAULT NULL, ADD descendant_count INT DEFAULT NULL, ADD level INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66D7D721ED FOREIGN KEY (root_article_id) REFERENCES article (id)');
        $this->addSql('CREATE INDEX IDX_23A0E66D7D721ED ON article (root_article_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66D7D721ED');
        $this->addSql('DROP INDEX IDX_23A0E66D7D721ED ON article');
        $this->addSql('ALTER TABLE article DROP root_article_id, DROP children_count, DROP descendant_count, DROP level');
    }
}
