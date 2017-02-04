<?php
interface BcDbMigratorInterface {
	public function getMessage();
	public function migrateSchema();
	public function migrateData();
}