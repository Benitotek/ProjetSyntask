<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250614094821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration - synchronisation de la base existante';
    }

    public function up(Schema $schema): void
    {
        // Cette migration est intentionnellement vide
        // Elle sert de point de départ pour les futures migrations
        $this->addSql('-- Migration initiale pour synchroniser avec la base existante');
    }

    public function down(Schema $schema): void
    {
        // Cette méthode est intentionnellement vide
        $this->addSql('-- Pas de rollback pour la migration initiale');
    }
}
