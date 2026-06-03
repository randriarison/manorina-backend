<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603142408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commune (id INT AUTO_INCREMENT NOT NULL, country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', district_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, code VARCHAR(20) DEFAULT NULL, INDEX IDX_E2E2D1EEF92F3E70 (country_id), INDEX IDX_E2E2D1EEB08FA272 (district_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE continent (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', nom VARCHAR(255) NOT NULL, nom_en VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_6CC70C7C6C6E55B5 (nom), UNIQUE INDEX UNIQ_6CC70C7C8472BAFB (nom_en), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE district (id INT AUTO_INCREMENT NOT NULL, country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, code VARCHAR(20) DEFAULT NULL, INDEX IDX_31C15487F92F3E70 (country_id), INDEX IDX_31C1548798260155 (region_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fokontany (id INT AUTO_INCREMENT NOT NULL, country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', commune_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, code VARCHAR(20) DEFAULT NULL, INDEX IDX_E9452B46F92F3E70 (country_id), INDEX IDX_E9452B46131A4F72 (commune_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commune ADD CONSTRAINT FK_E2E2D1EEF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE commune ADD CONSTRAINT FK_E2E2D1EEB08FA272 FOREIGN KEY (district_id) REFERENCES district (id)');
        $this->addSql('ALTER TABLE district ADD CONSTRAINT FK_31C15487F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE district ADD CONSTRAINT FK_31C1548798260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('ALTER TABLE fokontany ADD CONSTRAINT FK_E9452B46F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE fokontany ADD CONSTRAINT FK_E9452B46131A4F72 FOREIGN KEY (commune_id) REFERENCES commune (id)');
        $this->addSql('ALTER TABLE country ADD continent_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD slug VARCHAR(255) NOT NULL, ADD code VARCHAR(255) DEFAULT NULL, ADD tel_code INT DEFAULT NULL, ADD currency VARCHAR(255) DEFAULT NULL, ADD currency_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE country ADD CONSTRAINT FK_5373C966921F4C77 FOREIGN KEY (continent_id) REFERENCES continent (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5373C966989D9B62 ON country (slug)');
        $this->addSql('CREATE INDEX IDX_5373C966921F4C77 ON country (continent_id)');
        $this->addSql('ALTER TABLE region ADD slug VARCHAR(255) NOT NULL, ADD code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD commune_of_residence_id INT DEFAULT NULL, ADD district_of_residence_id INT DEFAULT NULL, ADD fokontany_of_residence_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A726AA1 FOREIGN KEY (commune_of_residence_id) REFERENCES commune (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64936678463 FOREIGN KEY (district_of_residence_id) REFERENCES district (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649D164B63F FOREIGN KEY (fokontany_of_residence_id) REFERENCES fokontany (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649A726AA1 ON user (commune_of_residence_id)');
        $this->addSql('CREATE INDEX IDX_8D93D64936678463 ON user (district_of_residence_id)');
        $this->addSql('CREATE INDEX IDX_8D93D649D164B63F ON user (fokontany_of_residence_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A726AA1');
        $this->addSql('ALTER TABLE country DROP FOREIGN KEY FK_5373C966921F4C77');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64936678463');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649D164B63F');
        $this->addSql('ALTER TABLE commune DROP FOREIGN KEY FK_E2E2D1EEF92F3E70');
        $this->addSql('ALTER TABLE commune DROP FOREIGN KEY FK_E2E2D1EEB08FA272');
        $this->addSql('ALTER TABLE district DROP FOREIGN KEY FK_31C15487F92F3E70');
        $this->addSql('ALTER TABLE district DROP FOREIGN KEY FK_31C1548798260155');
        $this->addSql('ALTER TABLE fokontany DROP FOREIGN KEY FK_E9452B46F92F3E70');
        $this->addSql('ALTER TABLE fokontany DROP FOREIGN KEY FK_E9452B46131A4F72');
        $this->addSql('DROP TABLE commune');
        $this->addSql('DROP TABLE continent');
        $this->addSql('DROP TABLE district');
        $this->addSql('DROP TABLE fokontany');
        $this->addSql('DROP INDEX UNIQ_5373C966989D9B62 ON country');
        $this->addSql('DROP INDEX IDX_5373C966921F4C77 ON country');
        $this->addSql('ALTER TABLE country DROP continent_id, DROP slug, DROP code, DROP tel_code, DROP currency, DROP currency_code');
        $this->addSql('DROP INDEX IDX_8D93D649A726AA1 ON user');
        $this->addSql('DROP INDEX IDX_8D93D64936678463 ON user');
        $this->addSql('DROP INDEX IDX_8D93D649D164B63F ON user');
        $this->addSql('ALTER TABLE user DROP commune_of_residence_id, DROP district_of_residence_id, DROP fokontany_of_residence_id');
        $this->addSql('ALTER TABLE region DROP slug, DROP code');
    }
}
