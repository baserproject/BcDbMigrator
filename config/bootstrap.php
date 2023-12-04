<?php

use Cake\Log\Log;

Log::setConfig('migrate_db', [
	'className' => 'File',
	'path' => LOGS,
	'file' => 'migrate_db',
	'scopes' => ['migrate_db'],
	'levels' => ['info', 'error']
]);
