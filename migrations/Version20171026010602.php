<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171026010602 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up( Schema $schema ): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'CREATE TABLE event (event_id INT AUTO_INCREMENT NOT NULL, event_program_id INT NOT NULL, event_title VARCHAR(255) NOT NULL, event_start DATETIME DEFAULT NULL, event_end DATETIME DEFAULT NULL, event_timezone VARCHAR(64) DEFAULT \'UTC\' NOT NULL, event_updated_at DATETIME DEFAULT NULL, event_valid TINYINT(1) DEFAULT \'0\' NOT NULL, INDEX event_time (event_start, event_end), INDEX event_title (event_title), INDEX event_program (event_program_id), UNIQUE INDEX event_title_program_uniq (event_program_id, event_title), PRIMARY KEY(event_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE event_stat (es_id INT AUTO_INCREMENT NOT NULL, es_event_id INT NOT NULL, es_metric VARCHAR(32) NOT NULL, es_value INT DEFAULT 0 NOT NULL, INDEX es_metrics (es_event_id), UNIQUE INDEX es_event_metric (es_event_id, es_metric), PRIMARY KEY(es_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE event_wiki (ew_id INT AUTO_INCREMENT NOT NULL, ew_event_id INT NOT NULL, ew_dbname VARCHAR(32) NOT NULL, INDEX ew_event (ew_event_id), INDEX ew_wiki (ew_dbname), UNIQUE INDEX ew_event_wiki (ew_event_id, ew_dbname), PRIMARY KEY(ew_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE organizer (org_id INT AUTO_INCREMENT NOT NULL, org_user_id INT NOT NULL, INDEX org_user (org_user_id), PRIMARY KEY(org_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE participant (par_id INT AUTO_INCREMENT NOT NULL, par_event_id INT NOT NULL, par_user_id INT NOT NULL, par_new_editor TINYINT(1) NOT NULL, INDEX par_event (par_event_id), INDEX par_user (par_user_id), UNIQUE INDEX par_event_user (par_event_id, par_user_id), PRIMARY KEY(par_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE program (program_id INT AUTO_INCREMENT NOT NULL, program_title VARCHAR(255) NOT NULL, UNIQUE INDEX program_title_uniq (program_title), PRIMARY KEY(program_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'CREATE TABLE organizers_programs (program_id INT NOT NULL, org_id INT NOT NULL, INDEX IDX_C5D5C0623EB8070A (program_id), INDEX IDX_C5D5C062F4837C1B (org_id), PRIMARY KEY(program_id, org_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB' );
		$this->addSql( 'ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA76656DE25 FOREIGN KEY (event_program_id) REFERENCES program (program_id)' );
		$this->addSql( 'ALTER TABLE event_stat ADD CONSTRAINT FK_3F73B38AEE440C48 FOREIGN KEY (es_event_id) REFERENCES event (event_id)' );
		$this->addSql( 'ALTER TABLE event_wiki ADD CONSTRAINT FK_3D0690ADE7AFAC32 FOREIGN KEY (ew_event_id) REFERENCES event (event_id)' );
		$this->addSql( 'ALTER TABLE participant ADD CONSTRAINT FK_D79F6B118547E283 FOREIGN KEY (par_event_id) REFERENCES event (event_id)' );
		$this->addSql( 'ALTER TABLE organizers_programs ADD CONSTRAINT FK_C5D5C0623EB8070A FOREIGN KEY (program_id) REFERENCES program (program_id)' );
		$this->addSql( 'ALTER TABLE organizers_programs ADD CONSTRAINT FK_C5D5C062F4837C1B FOREIGN KEY (org_id) REFERENCES organizer (org_id)' );
	}

	/**
	 * @param Schema $schema
	 */
	public function down( Schema $schema ): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event_stat DROP FOREIGN KEY FK_3F73B38AEE440C48' );
		$this->addSql( 'ALTER TABLE event_wiki DROP FOREIGN KEY FK_3D0690ADE7AFAC32' );
		$this->addSql( 'ALTER TABLE participant DROP FOREIGN KEY FK_D79F6B118547E283' );
		$this->addSql( 'ALTER TABLE organizers_programs DROP FOREIGN KEY FK_C5D5C062F4837C1B' );
		$this->addSql( 'ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA76656DE25' );
		$this->addSql( 'ALTER TABLE organizers_programs DROP FOREIGN KEY FK_C5D5C0623EB8070A' );
		$this->addSql( 'DROP TABLE event' );
		$this->addSql( 'DROP TABLE event_stat' );
		$this->addSql( 'DROP TABLE event_wiki' );
		$this->addSql( 'DROP TABLE organizer' );
		$this->addSql( 'DROP TABLE participant' );
		$this->addSql( 'DROP TABLE program' );
		$this->addSql( 'DROP TABLE organizers_programs' );
	}
}
