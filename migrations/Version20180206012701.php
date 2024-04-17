<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180206012701 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up( Schema $schema ): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'CREATE TABLE event_wiki_stat (ews_id INT AUTO_INCREMENT NOT NULL, ews_event_wiki_id INT NOT NULL, ews_metric VARCHAR(32) NOT NULL, ews_metric_offset INT DEFAULT NULL, ews_value INT DEFAULT 0 NOT NULL, INDEX ews_event_wiki (ews_event_wiki_id), UNIQUE INDEX ews_metrics (ews_event_wiki_id, ews_metric), PRIMARY KEY(ews_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'ALTER TABLE event_wiki_stat ADD CONSTRAINT FK_260F01B52A32AC7E FOREIGN KEY (ews_event_wiki_id) REFERENCES event_wiki (ew_id)' );
	}

	/**
	 * @param Schema $schema
	 */
	public function down( Schema $schema ): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'DROP TABLE event_wiki_stat' );
	}
}
