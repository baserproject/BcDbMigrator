<?php
namespace BcDbMigrator\Controller\Component;
interface BcDbMigratorInterface {
	public function getMessage();
	public function migrateSchema();
	public function migrateData();
}