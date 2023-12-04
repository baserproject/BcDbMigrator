<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.7
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BcDbMigrator\Controller\Component;

use BaserCore\Service\BcDatabaseService;
use BaserCore\Service\BcDatabaseServiceInterface;
use BaserCore\Utility\BcContainerTrait;
use BaserCore\Utility\BcUtil;
use Cake\Core\Plugin as CakePlugin;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Core\Configure;
use Psr\Log\LogLevel;

/**
 * include files
 */

/**
 * BcDbMigratorComponent
 */
class BcDbMigratorComponent extends \Cake\Controller\Component
{
	
	/**
	 * Trait
	 */
	use BcContainerTrait;
	
	/**
	 * BcDatabaseService
	 * @var BcDatabaseServiceInterface
	 */
	protected $dbService;
	
	/**
	 * TableLocator
	 * @var
	 */
	protected $tableLocator;
	
	/**
	 * デフォルトプラグイン
	 *
	 * @var array
	 */
	protected $_defaultPlugins;
	
	/**
	 * エンコーディング
	 *
	 * @var string
	 */
	public $encoding;
	
	/**
	 * 新DBソース
	 * @var ConnectionInterface
	 */
	protected $_newDb;
	
	/**
	 * 旧DBソース
	 * @var ConnectionInterface
	 */
	protected $_oldDb;
	
	/**
	 * DBプレフィックス
	 * @var string
	 */
	public $newDbPrefix = 'bc_new_';
	public $oldDbPrefix = 'bc_old_';
	
	/**
	 * 一時ファイルを配置するパス
	 * @var string
	 */
	protected $tmpPath;
	
	/**
	 * DB接続名
	 *
	 * @var string
	 */
	public $newDbConfigKeyName = 'bcNewDbMigrator';
	public $oldDbConfigKeyName = 'bcOldDbMigrator';
	
	/**
	 * バックアップファイルにおけるコアのフォルダ名
	 * @var string 
	 */
	public $coreFolder = 'core';
	
	/**
	 * startup
	 *
	 * @param \Controller $controller
	 */
	public function startup(\Cake\Event\EventInterface $event)
	{
		$this->tableLocator = TableRegistry::getTableLocator();
		$this->dbService = $this->getService(BcDatabaseServiceInterface::class);
		$this->_defaultPlugins = [
			'BcBlog',
			'BcContentLink',
			'BcEditorTemplate',
			'BcFavorite',
			'BcMail',
			'BcSearchIndex',
			'BcThemeConfig',
			'BcThemeFile',
			'BcUploader',
			'BcWidgetArea',
		];
		$this->_newDb = $this->_createMigrationDb($this->newDbConfigKeyName, $this->newDbPrefix);
		$this->_oldDb = $this->_createMigrationDb($this->oldDbConfigKeyName, $this->oldDbPrefix);
		ini_set('memory_limit', '-1');
		set_time_limit(0);
	}
	
	/**
	 * マイグレーション実行
	 */
	public function migrate($encoding)
	{
		$this->encoding = $encoding;
		$this->_setUp();
		$this->log('マイグレーションの準備完了', LogLevel::INFO, 'migrate_db');
		$result = true;
		if ($this->migrateSchema()) {
			$this->log('スキーマのマイグレーション完了', LogLevel::INFO, 'migrate_db');
			if (!$this->migrateData()) {
				$result = false;
			} else {
				$this->log('データのマイグレーション完了', LogLevel::INFO, 'migrate_db');
			}
		} else {
			$result = false;
		}
		$this->_tearDown();
		return $result;
	}
	
	/**
	 * スキーマのマイグレーションを実行
	 */
	public function migrateSchema()
	{
		return true;
	}
	
	/**
	 * データのマイグレーションを実行する
	 */
	public function migrateData()
	{
		return true;
	}
	
	/**
	 * マイグレーション開始処理
	 */
	public function _setUp()
	{
		$this->tmpPath = $this->getController()->_tmpPath;
		/* @var ConnectionInterface $db */
		$db = ConnectionManager::get($this->oldDbConfigKeyName);
		$db->execute("SET SESSION sql_mode = ''");
		$this->backupPhinxlog();
		$this->_deleteMigrationTables();
		$this->_createMigrationTables();
		// バックアップデータの構成を再構築する
		$this->constructBackupData();
		$this->_setDbConfigToAllModels($this->newDbConfigKeyName);
	}
	
	/**
	 * phinxlogをバックアップする
	 * @return void
	 */
	public function backupPhinxlog()
	{
		$corePlugins = Configure::read('BcApp.corePlugins');
		array_unshift($corePlugins, 'BaserCore');
		foreach($corePlugins as $corePlugin) {
			$table = Inflector::underscore($corePlugin) . '_phinxlog';
			if ($this->dbService->tableExists($table)) {
				$this->dbService->renameTable($table, 'bak__' . $table);
			}
		}
	}
	
	/**
	 * phinxlogを復元する
	 * @return void
	 */
	public function restorePhinxlog()
	{
		$corePlugins = Configure::read('BcApp.corePlugins');
		array_unshift($corePlugins, 'BaserCore');
		foreach($corePlugins as $corePlugin) {
			$table = 'bak__' . Inflector::underscore($corePlugin);
			if ($this->dbService->tableExists($table)) {
				$this->dbService->renameTable($table, Inflector::underscore($corePlugin) . '_phinxlog');
			}
		}
	}
	
	/**
	 * マイグレーション終了処理
	 */
	public function _tearDown()
	{
		$this->_deleteMigrationTables();
		$this->_setDbConfigToAllModels('default');
		$this->restorePhinxlog();
	}
	
	/**
	 * マイグレーション用のテーブルを生成する
	 */
	protected function _createMigrationTables()
	{
		// 新しいバージョンのテーブル
		$this->dbService->constructionTable('BaserCore', $this->newDbConfigKeyName);
		$this->dbService->loadDefaultDataPattern(\Cake\Core\Configure::read('BcApp.defaultFrontTheme'), 'default', $this->newDbConfigKeyName);
		
		$pluginCollection = CakePlugin::getCollection();
		foreach($this->_defaultPlugins as $plugin) {
			$pluginClass = $pluginCollection->create($plugin);
			$pluginClass->install(['connection' => $this->newDbConfigKeyName]);
		}
		
		BcUtil::clearAllCache();
		TableRegistry::getTableLocator()->clear();
		
		// 古いバージョンのテーブル
		$this->_createTableBySchema($this->_oldDb, $this->tmpPath . $this->coreFolder);
		$this->_createTableBySchema($this->_oldDb, $this->tmpPath . 'plugin');
		$this->_loadCsv($this->_oldDb, $this->tmpPath . $this->coreFolder);
		$this->_loadCsv($this->_oldDb, $this->tmpPath . 'plugin');
		
		BcUtil::clearAllCache();
		TableRegistry::getTableLocator()->clear();
	}
	
	/**
	 * スキーマファイルからテーブルを生成する
	 *
	 * @param ConnectionInterface $db
	 * @param string $path
	 * @return bool
	 */
	protected function _createTableBySchema($db, $path)
	{
		// BcMigration が、AppTableより接続を取得する仕様のため、一旦切り替える 
		$appTable = TableRegistry::getTableLocator()->get('BaserCore.App');
		$currentDb = $appTable->getConnection();
		$appTable->setConnection($db);
		$Folder = new Folder($path);
		$prefix = $db->config()['prefix'];
		$files = $Folder->read(true, true, true);
		if (!empty($files[1])) {
			foreach($files[1] as $file) {
				if (preg_match('/\.php$/', $file)) {
					$tableName = basename($file, '.php');
					$File = new File($file);
					$contents = $File->read();
					$contents = preg_replace('/class (.+?)Schema/', 'class ${1}OldSchema', $contents);
					$contents = preg_replace('/extends CakeSchema/', 'extends \BaserCore\Database\Schema\BcSchema', $contents);
					$contents = preg_replace('/\$' . $tableName . '/', '$fields', $contents);
					$contents = preg_replace('/\'indexes\' => /', "'_constraints' => ", $contents);
					$contents = preg_replace("/'column' => 'id'/", "'type' => 'primary', 'columns' => ['id']", $contents);
					$contents = preg_replace('/\'indexes\' => /', "'_constraints' => ", $contents);
					$contents = preg_replace('/\'tableParameters\' => /', "'_options' => ", $contents);
					$contents = preg_replace('/public \$file = .+?;/', "public \$table = '{$tableName}';", $contents);
					$contents = str_replace("'blog_content_id_no_index' => array('column' => array('blog_content_id', 'no'), 'unique' => 1)", '', $contents);
					
					$File->write($contents);
					$File->close();
					$old = dirname($file) . DS . Inflector::camelize($tableName) . 'OldSchema.php';
					$new = dirname($file) . DS . Inflector::camelize($tableName) . 'Schema.php';
					rename($file, $old);
					$this->dbService->loadSchema([
						'type' => 'create',
						'path' => $path . DS,
						'file' => basename($old),
						'prefix' => $prefix
					]);
					rename($old, $new);
					$File = new File($new);
					$contents = $File->read();
					$contents = preg_replace('/class (.+?)OldSchema/', 'class ${1}Schema', $contents);
					$File->write($contents);
					$File->close();
				}
			}
		}
		$appTable->setConnection($currentDb);
		return true;
	}
	
	/**
	 * CSVファイルをデータベースに読み込む
	 *
	 * @param ConnectionInterface $db
	 * @param string $path
	 * @return bool
	 */
	protected function _loadCsv($db, $path)
	{
		$Folder = new Folder($path);
		$files = $Folder->read(true, true, true);
		if (!empty($files[1])) {
			foreach($files[1] as $file) {
				if (preg_match('/\.csv/', $file)) {
					try {
						$this->dbService->loadCsv([
							'path' => $file,
							'encoding' => $this->encoding,
							'dbConfigKeyName' => $db->configName()
						]);
					} catch (\Exception $e) {
					}
				}
			}
		}
		return true;
	}
	
	/**
	 * マイグレーション用のDBソースを生成する
	 */
	protected function _createMigrationDb($dbConfigKeyName, $prefix): ConnectionInterface
	{
		$dbConfig = ConnectionManager::getConfig('default');
		$dbConfig['prefix'] = $prefix;
		ConnectionManager::setConfig($dbConfigKeyName, $dbConfig);
		return ConnectionManager::get($dbConfigKeyName);
	}
	
	/**
	 * 全てモデルにDB設定キーの設定を行う
	 *
	 * @param string $dbConfigKeyName DB設定キー
	 */
	protected function _setDbConfigToAllModels($dbConfigKeyName)
	{
		TableRegistry::getTableLocator()->clear();
		TableRegistry::getTableLocator()->get('BaserCore.App', ['connectionName' => $dbConfigKeyName]);
		$this->_setDbConfigToModels('BaserCore', $this->getTables('BaserCore'), $dbConfigKeyName);
		$collection = CakePlugin::getCollection();
		foreach($this->_defaultPlugins as $plugin) {
			if ($collection->get($plugin)) {
				$this->_setDbConfigToModels($plugin, $this->getTables($plugin), $dbConfigKeyName);
			}
		}
	}
	
	/**
	 * テーブル一覧を取得する
	 * @param string $plugin
	 * @return array
	 */
	private function getTables(string $plugin): array
	{
		$Folder = new Folder(CakePlugin::path($plugin) . 'src' . DS . 'Model' . DS . 'Table');
		$files = $Folder->read(true, true, true);
		$tables = [];
		foreach($files[1] as $file) {
			if (preg_match('/\.php$/', $file)) {
				$tables[] = basename($file, '.php');
			}
		}
		return $tables;
	}
	
	/**
	 * 指定したモデルにDB設定を行う
	 *
	 * @param array $models モデル群
	 * @param string $dbConfigKeyName DB設定キー
	 * @return bool
	 */
	protected function _setDbConfigToModels($plugin, $models, $dbConfigKeyName)
	{
		$excludes = ['AppTable'];
		if (!$models) {
			return false;
		}
		foreach($models as $model) {
			if (!in_array($model, $excludes)) {
				if ($plugin) {
					$model = $plugin . '.' . preg_replace('/Table$/', '', $model);
				}
				try {
					if(TableRegistry::getTableLocator()->exists($model)) {
						TableRegistry::getTableLocator()->remove($model);
					}
					TableRegistry::getTableLocator()->get($model, ['connectionName' => $dbConfigKeyName]);
				} catch (\Exception $e) {
				}
			}
		}
		return true;
	}
	
	/**
	 * マイグレーション用のテーブルを削除する
	 */
	protected function _deleteMigrationTables()
	{
		$this->dbService->deleteTables($this->newDbConfigKeyName);
		$this->dbService->deleteTables($this->oldDbConfigKeyName);
	}
	
	/**
	 * CSVを配列として読み込む
	 *
	 * @param string $table テーブル名
	 * @return mixed
	 */
	public function readCsv($table)
	{
		return $this->dbService->loadCsvToArray($this->tmpPath . $table . '.csv', $this->encoding);
	}
	
	/**
	 * スキーマファイルを削除する
	 *
	 * @param string $table
	 */
	public function deleteSchema($table)
	{
		$path = $this->tmpPath . $table . 'Schema.php';
		if (file_exists($path)) {
			unlink($path);
		}
	}
	
	/**
	 * データファイルを削除する
	 */
	public function deleteCsv($table)
	{
		unlink($this->tmpPath . $table . '.csv');
	}
	
	/**
	 * コアのスキーマで上書きする
	 */
	public function writeNewSchema()
	{
		/* @var BcDatabaseService $dbService */
		$dbService = $this->getService(BcDatabaseServiceInterface::class);
		/* @var \Cake\Database\Connection $db */
		$db = ConnectionManager::get($this->newDbConfigKeyName);
		$tables = $db->getSchemaCollection()->listTables();
		foreach($tables as $table) {
			if (preg_match('/^' . $this->newDbPrefix . '/', $table)) continue;
			if (preg_match('/^' . $this->oldDbPrefix . '/', $table)) continue;
			if (preg_match('/phinxlog$/', $table)) continue;
			if (!$dbService->writeSchema($table, [
				'path' => $this->tmpPath
			])) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * バックアップデータの構成を再構築する
	 * @return void
	 */
	public function constructBackupData()
	{
		$folder = new Folder($this->tmpPath . $this->coreFolder);
		$files = $folder->read(true, true, true);
		foreach($files[1] as $file) {
			rename($file, $this->tmpPath . basename($file));
		}
		$folder->delete();
		
		$folder = new Folder($this->tmpPath . 'plugin');
		$files = $folder->read(true, true, true);
		foreach($files[1] as $file) {
			rename($file, $this->tmpPath . basename($file));
		}
		$folder->delete();
	}
	
	/**
	 * マイグレーションテーブルよりCSVを書き出す
	 * @param string $table
	 */
	public function writeCsv($table)
	{
		$this->tableLocator->remove('BaserCore.App');
		$this->tableLocator->get('BaserCore.App');
		// CSVを書き出す
		// BcDatabaseService::writeCsv() が、別のDB接続に対応していないため
		// プレフィックス付で書き出しで指定している
		$this->dbService->writeCsv($this->newDbPrefix . $table, [
			'path' => $this->tmpPath . $table . '.csv',
			'encoding' => 'UTF-8',
			'init' => false,
		]);
	}
	
}
