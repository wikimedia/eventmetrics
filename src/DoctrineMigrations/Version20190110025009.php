<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190110025009 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX job_started ON job');
        $this->addSql('ALTER TABLE job ADD job_status SMALLINT DEFAULT 0 NOT NULL, DROP job_started');
        $this->addSql('CREATE INDEX job_status ON job (job_status)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX job_status ON job');
        $this->addSql('ALTER TABLE job ADD job_started TINYINT(1) DEFAULT \'0\' NOT NULL, DROP job_status');
        $this->addSql('CREATE INDEX job_started ON job (job_started)');
    }
}
