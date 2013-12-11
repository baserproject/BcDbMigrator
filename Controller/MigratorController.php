<?php
class MigratorController extends BcPluginAppController {
	
/**
 * コンポーネント
 * 
 * @var array
 * @access public
 */
	public $components = array('BcAuth', 'Cookie', 'BcAuthConfigure');

/**
 * 一時フォルダ
 * @var string
 */
	protected $_tmpPath;
	
/**
 * モデル
 * @var array
 */
	public $uses = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->_tmpPath = TMP . 'dbmigrator' . DS;
	}
	
	public function admin_index() {
		$this->pageTitle = 'baserCMS DBマイグレーター';
		if($this->request->data) {
			set_time_limit(0);
			$this->setMessage('バックアップデータのマイグレーションが完了しました。ダウンロードボタンよりダウンロードしてください。');
			$this->_migrate($this->request->data);
			$this->redirect('index');
		}
		if($this->Session->read('BcDbMigrator.downloaded')) {
			$this->Session->delete('BcDbMigrator.file');
			$this->Session->delete('BcDbMigrator.downloaded');
			$Folder = new Folder($this->_tmpPath);
			$Folder->delete();
		}
	}
	
	protected function _migrate ($data) {
		
		App::uses('Simplezip', 'Vendor');
		
		if (empty($data['Migrator']['backup']['tmp_name'])) {
			return false;
		}

		$tmpPath = $this->_tmpPath;
		$Folder = new Folder();
		$Folder->create($tmpPath, 0777);
		
		$targetPath = $tmpPath . $data['Migrator']['backup']['name'];
		if (!move_uploaded_file($data['Migrator']['backup']['tmp_name'], $targetPath)) {
			return false;
		}

		/* ZIPファイルを解凍する */
		$Simplezip = new Simplezip();
		if (!$Simplezip->unzip($targetPath, $tmpPath)) {
			return false;
		}

		@unlink($targetPath);
		
		// スキーマ更新
		$this->_migrateSchema();

		// データ更新
		$this->_migrateData();
		
		$version = str_replace(' ', '_', $this->getBaserVersion());
		$fileName = 'baserbackup_' . $version . '_' . date('Ymd_His');
		$this->Session->write('BcDbMigrator.file', $fileName);
		
		return true;
		
	}
	
	public function admin_download() {
		$fileName = $this->Session->read('BcDbMigrator.file');
		
		if(!$fileName || !is_dir($this->_tmpPath)) {
			$this->notFound();
		}
		
		App::uses('Simplezip', 'Vendor');
		// ZIP圧縮
		$Simplezip = new Simplezip();
		$Simplezip->addFolder($this->_tmpPath);
		
		// ダウンロード
		$Simplezip->download($fileName);
		$this->Session->write('BcDbMigrator.downloaded', true);
	}
	
	protected function _migrateSchema() {
		$tmpPath = $this->_tmpPath;
		$sourcePath = BASER_CONFIGS . 'sql';
		$Folder = new Folder($sourcePath);
		$files = $Folder->read(true, true, true);
		foreach($files[1] as $file) {
			copy($file, $tmpPath . 'baser' . DS . basename($file));
		}
		$plugins = array('Blog', 'Mail', 'Feed');
		foreach($plugins as $plugin) {
			$sourcePath = BASER_PLUGINS . $plugin . DS . 'Config' . DS . 'sql';
			$Folder = new Folder($sourcePath);
			$files = $Folder->read(true, true, true);
			foreach($files[1] as $file) {
				if(basename($file) != 'contact_messages.php' && basename($file) != 'messages.php')
				copy($file, $tmpPath . 'plugin' . DS . basename($file));
			}
		}
		@unlink($tmpPath . 'baser' . DS . 'global_menus.php');
	}
	
	protected function _migrateData() {
		$tmpPath = $this->_tmpPath;
		
		// menus
		@rename($tmpPath . 'baser' . DS . 'global_menus.csv', $tmpPath . 'baser' . DS . 'menus.csv');
		
		// theme_configs
		copy(BASER_CONFIGS . 'data' . DS . 'default' . DS . 'theme_configs.csv', $tmpPath . 'baser' . DS . 'theme_configs.csv');
		
		// site_configs.csv
		$File = new File($tmpPath . DS . 'baser' . DS . 'site_configs.csv');
		$data = $File->read();
		$data = preg_replace('/"version","2\.1\.[0-9]"/', '"version","3.0.0"', $data);
		$data .= '"","editor","BcCkeditor","",""';
		$File->write($data);
		$File->close();
		
		// pages.csv
		$File = new File($tmpPath . DS . 'baser' . DS . 'pages.csv');
		$data = $File->read();
		$data = str_replace('$bcBaser', '$this->BcBaser', $data);
		$data = preg_replace("/src=\"\"\/themed\//is", 'src=""/theme/', $data);
		$File->write($data);
		$File->close();
		
		// plugins.csv
		$File = new File($tmpPath . DS . 'baser' . DS . 'plugins.csv');
		$data = $File->read();
		$data = preg_replace("/\n\"([0-9]+)\",\"([a-zA-Z_\-]+)\",\"(.*?)\",\"([0-9\.]+)\",\"([01]*?)\",\"([01]*?)\",\"(.*?)\",\"(.*?)\"/is", 
				"\n\"$1\",\"$2\",\"$3\",\"$4\",\"0\",\"$6\",\"$7\",\"$8\"", $data);
		$data = preg_replace("/\"blog\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"blog\",\"$1\",\"3.0.0\"", $data);
		$data = preg_replace("/\"mail\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"mail\",\"$1\",\"3.0.0\"", $data);
		$data = preg_replace("/\"feed\",\"(.*?)\",\"([0-9\.]+)\"/is", "\"feed\",\"$1\",\"3.0.0\"", $data);
		$File->write($data);
		$File->close();
		
	}
	
}
