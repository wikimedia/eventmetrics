<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171213050301 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up( Schema $schema ): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event CHANGE event_valid event_valid TINYINT(1) DEFAULT \'1\' NOT NULL' );
		$this->addSql( 'CREATE UNIQUE INDEX UNIQ_3BAE0AA76A2DC947 ON event (event_title)' );
		$this->addSql( 'DROP INDEX ew_wiki ON event_wiki' );
		$this->addSql( 'DROP INDEX ew_event_wiki ON event_wiki' );
		$this->addSql( 'ALTER TABLE event_wiki ADD ew_domain VARCHAR(255) NOT NULL, DROP ew_dbname' );
		$this->addSql( 'CREATE INDEX ew_domain ON event_wiki (ew_domain)' );
		$this->addSql( 'CREATE UNIQUE INDEX ew_event_wiki ON event_wiki (ew_event_id, ew_domain)' );
		$this->addSql( 'CREATE INDEX program_title ON program (program_title)' );
	}

	/**
	 * @param Schema $schema
	 */
	public function down( Schema $schema ): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'DROP INDEX UNIQ_3BAE0AA76A2DC947 ON event' );
		$this->addSql( 'ALTER TABLE event CHANGE event_valid event_valid TINYINT(1) DEFAULT \'0\' NOT NULL' );
		$this->addSql( 'DROP INDEX ew_domain ON event_wiki' );
		$this->addSql( 'DROP INDEX ew_event_wiki ON event_wiki' );
		$this->addSql( 'ALTER TABLE event_wiki ADD ew_dbname VARCHAR(32) NOT NULL COLLATE utf8_unicode_ci, DROP ew_domain' );
		$this->addSql( 'CREATE INDEX ew_wiki ON event_wiki (ew_dbname)' );
		$this->addSql( 'CREATE UNIQUE INDEX ew_event_wiki ON event_wiki (ew_event_id, ew_dbname)' );
		$this->addSql( 'DROP INDEX program_title ON program' );
	}
}
