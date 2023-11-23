<?php
return [
	'type' => 'Plugin',
	'title' => 'baserCMS DBマイグレーター',
	'description' => 'baserCMSバックアップデータを新しいバージョンのバックアップデータに変換します',
	'author' => 'baserCMS Users Community',
	'url' => 'http://basercms.net',
	'adminLink' => [  'admin' => true,  'plugin' => 'BcDbMigrator',  'controller' => 'Migrator',  'action' => 'index',],
	'installMessage' => '',
];
