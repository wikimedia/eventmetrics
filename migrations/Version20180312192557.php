<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180312192557 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up( Schema $schema ): void {
		$this->addSql( 'UPDATE event_stat SET es_metric_offset = 15 WHERE es_metric = "new-editors"' );
	}

	/**
	 * @param Schema $schema
	 */
	public function down( Schema $schema ): void {
		$this->addSql( 'UPDATE event_stat SET es_metric_offset = NULL WHERE es_metric = "new-editors"' );
	}
}
