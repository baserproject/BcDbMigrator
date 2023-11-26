<?php

namespace BcDbMigrator\Controller\Component;

use BaserCore\Service\BcDatabaseService;
use BaserCore\Service\BcDatabaseServiceInterface;
use BaserCore\Utility\BcContainerTrait;
use Cake\Core\Plugin as CakePlugin;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

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
	private $dbService;
	
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
	private $tmpPath;
	
	/**
	 * DB接続名
	 *
	 * @var string
	 */
	public $newDbConfigKeyName = 'bcNewDbMigrator';
	public $oldDbConfigKeyName = 'bcOldDbMigrator';
	
	public $coreFolder = 'core';
	
	/**
	 * startup
	 *
	 * @param \Controller $controller
	 */
	public function startup(\Cake\Event\EventInterface $event)
	{
		$this->dbService = $this->getService(BcDatabaseServiceInterface::class);
		$this->_defaultPlugins = \Cake\Core\Configure::read('BcApp.corePlugins');
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
		$result = true;
//		if ($this->migrateSchema()) {
//			if (!$this->migrateData()) {
//				$result = false;
//			}
//		} else {
//			$result = false;
//		}
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
		$this->_deleteMigrationTables();
		$this->_createMigrationTables();
		$this->_setDbConfigToAllModels($this->newDbConfigKeyName);
	}
	
	/**
	 * マイグレーション終了処理
	 */
	public function _tearDown()
	{
		$this->_deleteMigrationTables();
		$this->_setDbConfigToAllModels('default');
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
		
		\BaserCore\Utility\BcUtil::clearAllCache();
		TableRegistry::getTableLocator()->clear();
		
		// 古いバージョンのテーブル
		$this->_createTableBySchema($this->_oldDb, $this->tmpPath . $this->coreFolder);
		$this->_createTableBySchema($this->_oldDb, $this->tmpPath . 'plugin');
		$this->_loadCsv($this->_oldDb, $this->tmpPath . $this->coreFolder);
		$this->_loadCsv($this->_oldDb, $this->tmpPath . 'plugin');
		
		\BaserCore\Utility\BcUtil::clearAllCache();
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
		$Folder = new \Cake\Filesystem\Folder($path);
		$prefix = $db->config()['prefix'];
		$files = $Folder->read(true, true, true);
		if (!empty($files[1])) {
			foreach($files[1] as $file) {
				if (preg_match('/\.php$/', $file)) {
					$tableName = basename($file, '.php');
					$File = new \Cake\Filesystem\File($file);
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
					$File = new \Cake\Filesystem\File($new);
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
		$Folder = new \Cake\Filesystem\Folder($path);
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
		$dbConfig = \Cake\Datasource\ConnectionManager::getConfig('default');
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
		$this->_setDbConfigToModels('BaserCore', $this->getTables('BaserCore'), $dbConfigKeyName);
		$collection = CakePlugin::getCollection();
		foreach($this->_defaultPlugins as $plugin) {
			if($collection->get($plugin)) {
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
		$Folder = new \Cake\Filesystem\Folder(\Cake\Core\Plugin::path($plugin) . 'src' . DS . 'Model' . DS . 'Table');
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
					\Cake\ORM\TableRegistry::getTableLocator()->get($model, ['connectionName' => $dbConfigKeyName]);
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
	 * @param bool $isPlugin プラグインかどうか
	 * @param string $table テーブル名
	 * @return mixed
	 */
	public function readCsv($isPlugin, $table)
	{
		if (!$isPlugin) {
			$type = $this->coreFolder;
		} else {
			$type = 'plugin';
		}
		return $this->_newDb->loadCsvToArray($this->tmpPath . $type . DS . $table . '.csv', $this->encoding);
	}
	
	/**
	 * スキーマファイルを削除する
	 *
	 * @param bool $isPlugin
	 * @param string $table
	 */
	public function deleteSchema($isPlugin, $table)
	{
		if (!$isPlugin) {
			$type = $this->coreFolder;
		} else {
			$type = 'plugin';
		}
		$path = $this->tmpPath . $type . DS . $table . '.php';
		if (file_exists($path)) {
			unlink($path);
		}
	}
	
	/**
	 * データファイルを削除する
	 *
	 * @param bool $isPlugin
	 * @param string $table
	 */
	public function deleteCsv($isPlugin, $table)
	{
		if (!$isPlugin) {
			$type = $this->coreFolder;
		} else {
			$type = 'plugin';
		}
		unlink($this->tmpPath . $type . DS . $table . '.csv');
	}
	
	/**
	 * コアのスキーマで上書きする
	 */
	public function writeNewSchema()
	{
		$path = BASER_CONFIGS . 'Schema' . DS;
		$Folder = new \Cake\Filesystem\Folder($path);
		$files = $Folder->read(true, true, true);
		foreach($files[1] as $file) {
			copy($file, $this->tmpPath . $this->coreFolder . DS . basename($file));
		}
		foreach($this->_defaultPlugins as $plugin) {
			$path = BASER_PLUGINS . $plugin . DS . 'Config' . DS . 'schema' . DS;
			$Folder = new \Cake\Filesystem\Folder($path);
			$files = $Folder->read(true, true, true);
			foreach($files[1] as $file) {
				copy($file, $this->tmpPath . 'plugin' . DS . basename($file));
			}
		}
	}
	
	/**
	 * マイグレーションテーブルよりCSVを書き出す
	 *
	 * @param string $table
	 */
	public function writeCsv($isPlugin, $table)
	{
		if (!$isPlugin) {
			$type = $this->coreFolder;
		} else {
			$type = 'plugin';
		}
		// CSVを書き出す
		$this->_newDb->writeCsv(
			[
				'path' => $this->tmpPath . $type . DS . $table . '.csv',
				'encoding' => 'UTF-8',
				'table' => $table,
				'init' => false,
				'plugin' => null
			]
		);
	}
	
}
