<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624092411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE2C9E1458
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_2FB3D0EE2C9E1458 ON project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project CHANGE chef_de_projet_id chef_projet_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EED3B0D67CD3B0D67C FOREIGN KEY (chef_projet_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2FB3D0EED3B0D67C ON project (chef_projet_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EED3B0D67CD3B0D67C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_2FB3D0EED3B0D67C ON project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project CHANGE chef_projet_id chef_de_projet_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE2C9E1458 FOREIGN KEY (chef_de_projet_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2FB3D0EE2C9E1458 ON project (chef_de_projet_id)
        SQL);
    }
}
