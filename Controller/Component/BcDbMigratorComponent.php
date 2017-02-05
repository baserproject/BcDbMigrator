<?php
/**
 * include files
 */
App::uses('Component', 'Controller');

/**
 * BcDbMigratorComponent
 */
class BcDbMigratorComponent extends Component {

/**
 * コントローラー
 * 
 * @var Controller
 */
	protected $_Controller;

/**
 * デフォルトプラグイン
 * 
 * @var array
 */
	protected $_defaultPlugins;

/**
 * DBソース
 * 
 * @var DboSource
 */
	protected $_newDb;
	protected $_oldDb;

/**
 * DBプレフィックス
 * @var string
 */
	public $newDbPrefix = 'bc_new_';
	public $oldDbPrefix = 'bc_old_';

/**
 * DB接続名
 * 
 * @var string
 */
	public $newDbConfigKeyName = 'bcNewDbMigrator';
	public $oldDbConfigKeyName = 'bcOldDbMigrator';
	
/**
 * startup
 * 
 * @param \Controller $controller
 */
	public function startup(Controller $controller) {
		parent::startup($controller);
		$this->_Controller = $controller;
		$this->_defaultPlugins = Configure::read('BcApp.corePlugins');
		$this->_newDb = $this->_createMigrationDb($this->newDbConfigKeyName, $this->newDbPrefix);
		$this->_oldDb = $this->_createMigrationDb($this->oldDbConfigKeyName, $this->oldDbPrefix);
	}
	
/**
 * マイグレーション実行
 */
	public function migrate() {
		$this->_setUp();
		$result = true;
		if($this->migrateSchema()) {
			if(!$this->migrateData()) {
				$result = false;
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
	public function migrateSchema() {
		return true;
	}

/**
 * データのマイグレーションを実行する
 */
	public function migrateData() {
		return true;
	}

/**
 * マイグレーション開始処理 
 */
	public function _setUp() {
		$this->_createMigrationTables();
		$this->_setDbConfigToAllModels($this->newDbConfigKeyName);
	}

/**
 * マイグレーション終了処理 
 */
	public function _tearDown() {
		$this->_deleteMigrationTables();
		$this->_setDbConfigToAllModels('default');
	}

/**
 * マイグレーション用のテーブルを生成する
 */
	protected function _createMigrationTables() {
		
		// 新しいバージョンのテーブル
		$this->_Controller->BcManager->constructionTable('Core', $this->newDbConfigKeyName);
		$this->_Controller->BcManager->loadDefaultDataPattern($this->newDbConfigKeyName, null, 'default', 'core', 'core');
		$Plugin = ClassRegistry::init('Plugin');
		$Plugin->useDbConfig = $this->newDbConfigKeyName;
		foreach($this->_defaultPlugins as $plugin) {
			$Plugin->initDb($plugin);
		}

		clearAllCache();
		ClassRegistry::flush();
		
		// 古いバージョンのテーブル
		$this->_createTableBySchema($this->_oldDb, $this->_Controller->_tmpPath . 'baser');
		$this->_createTableBySchema($this->_oldDb, $this->_Controller->_tmpPath . 'plugin');
		$this->_loadCsv($this->_oldDb, $this->_Controller->_tmpPath . 'baser');
		$this->_loadCsv($this->_oldDb, $this->_Controller->_tmpPath . 'plugin');

		clearAllCache();
		ClassRegistry::flush();
		
	}

/**
 * スキーマファイルからテーブルを生成する
 * 
 * @param DboSource $db
 * @param string $path
 * @return bool
 */
	protected function _createTableBySchema($db, $path) {
		$Folder = new Folder($path);
		$files = $Folder->read(true, true, true);
		if(!empty($files[1])) {
			foreacH($files[1] as $file) {
				if(preg_match('/\.php$/', $file)) {
					$db->createTableBySchema([
						'path' => $file,
					]);
				}
			}
		}
		return true;
	}

/**
 * CSVファイルをデータベースに読み込む
 * 
 * @param DboSource $db
 * @param string $path
 * @return bool
 */
	protected function _loadCsv($db, $path) {
		$Folder = new Folder($path);
		$files = $Folder->read(true, true, true);
		if(!empty($files[1])) {
			foreacH($files[1] as $file) {
				if(preg_match('/\.csv/', $file)) {
					$db->loadCsv([
						'path' => $file,
						'encoding' => 'SJIS'
					]);
				}
			}
		}
		return true;
	}

/**
 * マイグレーション用のDBソースを生成する
 * 
 * @return \DataSource|null
 */
	protected function _createMigrationDb($dbConfigKeyName, $prefix) {
		$dbConfig = ConnectionManager::$config->default;
		$dbConfig['prefix'] = $prefix;
		return ConnectionManager::create($dbConfigKeyName, $dbConfig);
	}

/**
 * 全てモデルにDB設定キーの設定を行う
 *
 * @param string $dbConfigKeyName DB設定キー
 */
	protected function _setDbConfigToAllModels($dbConfigKeyName) {
		$this->_setDbConfigToModels(App::objects('Model'), $dbConfigKeyName);
		foreach($this->_defaultPlugins as $plugin) {
			$this->_setDbConfigToModels(App::objects($plugin . '.Model', null, false), $dbConfigKeyName);
		}
	}

/**
 * 指定したモデルにDB設定を行う
 *
 * @param array $models モデル群
 * @param string $dbConfigKeyName DB設定キー 
 * @return bool
 */
	protected function _setDbConfigToModels($models, $dbConfigKeyName) {
		$excludes = ['AppModel', 'BcAppModel', 'BcPluginAppModel' ,'CakeSchema'];
		if(!$models) {
			return false;
		}
		foreach($models as $model) {
			if(!in_array($model, $excludes)) {
				$modelClass = ClassRegistry::init($model);
				$this->_setDbConfigToModel($modelClass, $dbConfigKeyName);
			}
		}
		return true;
	}

/**
 * モデルにDB設定を行う
 * @param Model $Model
 * @param $dbConfigKeyName
 */
	protected function _setDbConfigToModel($Model, $dbConfigKeyName) {
		$dbConfig = ConnectionManager::$config->{$dbConfigKeyName};
		$Model->useDbConfig = $dbConfigKeyName;
		$Model->tablePrefix = $dbConfig['prefix'];
	}

/**
 * マイグレーション用のテーブルを削除する 
 */
	protected function _deleteMigrationTables() {
		$this->_Controller->BcManager->deleteTables($this->newDbConfigKeyName);
		$this->_Controller->BcManager->deleteTables($this->oldDbConfigKeyName);
	}

/**
 * CSVを配列として読み込む
 * 
 * @param bool $isPlugin プラグインかどうか
 * @param string $table テーブル名
 * @return mixed
 */
	public function readCsv($isPlugin, $table) {
		if(!$isPlugin) {
			$type = 'baser';
		} else {
			$type = 'plugin';
		}
		return $this->_newDb->loadCsvToArray($this->_Controller->_tmpPath . $type . DS . $table . '.csv', 'SJIS');
	}

	public function dummy() {
		
//		// CSVを書き出す
//		$db->writeCsv(
//			[
//				'path' => $this->_tmpPath . 'baser' . DS . 'contents_2.csv',
//				'encoding' => '',
//				'table' => 'contents',
//				'init' => false,
//				'plugin' => null
//			]
//		);

	}
	
}