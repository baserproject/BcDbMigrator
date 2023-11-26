<?php

namespace BcDbMigrator\Controller\Component;

/**
 * BcDbMigratorInterface
 */
interface BcDbMigratorInterface
{
	public function getMessage();
	
	public function migrateSchema();
	
	public function migrateData();
}
