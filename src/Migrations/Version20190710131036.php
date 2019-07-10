<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190710131036 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE project CHANGE job_id job_id INT NOT NULL');
        $this->addSql('ALTER TABLE task CHANGE default_time default_time NUMERIC(6, 2) NOT NULL');
        $this->addSql('ALTER TABLE fos_user CHANGE job_id job_id INT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE fos_user CHANGE job_id job_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project CHANGE job_id job_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task CHANGE default_time default_time INT NOT NULL');
    }
}
